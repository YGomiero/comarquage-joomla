/** Comarquage imports: bounded requests, persisted cursor and visible progress.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
document.addEventListener('DOMContentLoaded', () => {
    const original = Joomla.submitbutton;
    let running = false;
    Joomla.submitbutton = async (task) => {
        if (task !== 'importNow') { return original(task); }
        if (running) { return; }
        running = true;
        const form = document.getElementById('adminForm');
        const progress = document.createElement('div');
        progress.className = 'alert alert-info';
        progress.setAttribute('role', 'status');
        form.prepend(progress);
        let files = 0;
        try {
            let result;
            do {
                progress.textContent = `${Joomla.Text._('COM_COMARQUAGE_IMPORT_RUNNING')} ${files}`;
                const data = new FormData(form);
                data.set('task', 'importBatch');
                data.set('format', 'json');
                const response = await fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin'});
                if (!response.ok) { throw new Error(`HTTP ${response.status}`); }
                const json = await response.json();
                if (!json.success || json.data.errors) { throw new Error(json.message || json.data?.message || 'Import error'); }
                result = json.data;
                files += result.files;
            } while (result.interrupted);
            progress.textContent = Joomla.Text._('COM_COMARQUAGE_IMPORT_COMPLETE');
            window.location.reload();
        } catch (error) {
            progress.className = 'alert alert-warning';
            progress.textContent = `${Joomla.Text._('COM_COMARQUAGE_IMPORT_FAILED')} ${error.message}`;
        } finally { running = false; }
    };
});
