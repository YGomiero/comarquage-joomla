<?php
/** Regression tests for data integrity, XML/ZIP safety and public HTML. */
namespace Joomla\CMS\Filter {
    final class OutputFilter {
        public static function stringURLSafe($text): string {
            return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $text))), '-');
        }
    }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_ROOT', dirname(__DIR__));
    spl_autoload_register(static function ($class): void {
        foreach (['Administrator' => 'admin', 'Site' => 'site'] as $client => $dir) {
            $prefix = 'Joomla\\Component\\Comarquage\\' . $client . '\\';
            if (str_starts_with($class, $prefix)) {
                $file = JPATH_ROOT . '/component/' . $dir . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) { require $file; }
            }
        }
    });
    $count = 0;
    function check($condition, $message): void {
        global $count;
        if (!$condition) { throw new \RuntimeException($message); }
        $count++;
    }
    $base = sys_get_temp_dir() . '/comarquage_test_' . bin2hex(random_bytes(6));
    mkdir($base);
    $xml = '<Publication xmlns:dc="http://purl.org/dc/elements/1.1/" ID="F100" spUrl="https://www.service-public.gouv.fr/particuliers/vosdroits/F100" futureAttribute="accepted"><dc:title>Association test</dc:title><dc:date>modified 2026-10-25</dc:date><Theme ID="N31931"><Titre>Associations</Titre></Theme><Texte><Paragraphe>Texte <MiseEnEvidence>important</MiseEnEvidence><LienExterne URL="javascript:alert(1)">danger</LienExterne></Paragraphe><NewBlock>Future content</NewBlock></Texte></Publication>';
    $file = $base . '/record.xml'; file_put_contents($file, $xml);
    $parser = new \Joomla\Component\Comarquage\Administrator\Service\XmlParserService();
    $log = static function($level, $data): void { if ($level === 'error') { throw new \RuntimeException($data['message']); } };
    $result = $parser->parseFile($file, 'associations', '3.5', $log);
    check(count($result['items']) === 1, 'Current association theme must be selected');
    $item = $result['items'][0];
    check($item['external_id'] === 'F100', 'Stable official identifier');
    check($item['payload']['source_modified'] === 'modified 2026-10-25', 'Source editorial date');
    check(str_contains($item['payload']['structured_html'], 'Future content'), 'Unknown blocks retain text');
    check(!str_contains($item['payload']['structured_html'], 'javascript:'), 'Unsafe XML link rejected');
    file_put_contents($file, str_replace('N31931','N999',$xml));
    check(count($parser->parseFile($file, 'associations', '3.5', $log)['items']) === 0, 'Other themes excluded');
    file_put_contents($file, '<Arborescence><Dossier ID="F100"><Titre>Reference only</Titre></Dossier></Arborescence>');
    check(count($parser->parseFile($file, 'particuliers', '3.5', $log)['items']) === 0, 'Index references must never overwrite full records');
    file_put_contents($file, '<ServiceComplementaire ID="R100"><Titre>Online service</Titre><LienWeb URL="https://example.org/form"/></ServiceComplementaire>');
    $resource = $parser->parseFile($file, 'particuliers', '3.5', $log)['items'];
    check(count($resource) === 1 && str_contains($resource[0]['payload']['structured_html'], 'https://example.org/form'), 'Online resources retain the actual action link');
    foreach (['<!DOCTYPE x [<!ENTITY y SYSTEM "file:///etc/passwd">]><x>&y;</x>', '<Publication>'] as $bad) {
        file_put_contents($file, $bad); $rejected = false;
        try { $parser->parseFile($file, 'particuliers', '3.5', $log); } catch (\RuntimeException) { $rejected = true; }
        check($rejected, 'Invalid/entity XML rejected');
    }
    $renderer = new \Joomla\Component\Comarquage\Site\Service\ContentRendererService();
    $unsafe = '<p onclick="bad()">Safe</p><script>bad()</script><a href="jav&#x61;script:bad()">X</a><a href="https://example.org">Good</a><button data-comarquage-guide-choice data-comarquage-vars="A">Choice</button>';
    $html = $renderer->renderItem(['payload'=>json_encode(['structured_html'=>$unsafe])]);
    check(!str_contains($html,'onclick') && !str_contains($html,'<script') && !str_contains($html,'javascript:'), 'DOM sanitization');
    check(str_contains($html,'https://example.org') && str_contains($html,'data-comarquage-vars'), 'Safe links and guided decisions preserved');
    $storage = new \Joomla\Component\Comarquage\Administrator\Service\SourceStorageService();
    $zipfile = $base . '/unsafe.zip'; $zip = new \ZipArchive(); $zip->open($zipfile, \ZipArchive::CREATE); $zip->addFromString('../escape.xml',$xml); $zip->close();
    $rejected=false; try { $storage->prepare($zipfile); } catch (\RuntimeException) { $rejected=true; }
    check($rejected,'ZIP traversal rejected');
    $zipfile2 = $base . '/safe.zip'; $zip = new \ZipArchive(); $zip->open($zipfile2, \ZipArchive::CREATE); $zip->addFromString('folder/record.xml',$xml); $zip->addFromString('payload.php','<?php echo 1;'); $zip->close();
    $files=$storage->prepare($zipfile2);
    check(count($files)===1 && str_ends_with($files[0],'.xml'),'Only XML extracted');
    $storage->cleanup($files,$zipfile2);
    check(is_file($zipfile2) && !is_file($files[0]),'Local sources are never deleted');
    $url=\Joomla\Component\Comarquage\Administrator\Service\ImportService::sourceUrl('https://lecomarquage.service-public.fr/vdd/3.5/asso/zip/vosdroits-latest.zip');
    check($url==='https://lecomarquage.service-public.gouv.fr/vdd/3.5/part/zip/vosdroits-latest.zip','Frozen association archive migrated');
    $first=new \Joomla\Component\Comarquage\Administrator\Service\ImportLockService(); $second=new \Joomla\Component\Comarquage\Administrator\Service\ImportLockService();
    check($first->acquire() && !$second->acquire(),'Concurrent import rejected'); $first->release();$second->release();
    check($second->acquire(),'Lock released');$second->release();
    foreach (glob($base.'/*') as $file) { unlink($file); } rmdir($base);
    $validator = new \Joomla\Component\Comarquage\Administrator\Service\CommuneValidator();
    check($validator->isValidFormat('62119') && $validator->isValidFormat('2A004') && !$validator->isValidFormat('ABCDE'), 'INSEE mainland and Corsican formats');
    $record=['id'=>'demo','nom'=>'Mairie','pivot'=>'[{"type_service_local":"mairie"}]','code_insee_commune'=>'62119','url_service_public'=>'https://lannuaire.service-public.gouv.fr/hauts-de-france/pas-de-calais/demo'];
    $localClass=\Joomla\Component\Comarquage\Administrator\Service\LocalContextService::class;
    check($localClass::normalise($record,'62119')['type']==='mairie', 'Official local service normalisation');
    check($localClass::normalise($record,'75056')===null, 'Foreign municipality excluded');
    $record['url_service_public']='javascript:alert(1)';
    check($localClass::normalise($record,'62119')===null, 'Unsafe directory links rejected');
    $menus=[['id'=>10,'language'=>'*','access'=>1],['id'=>20,'language'=>'fr-FR','access'=>1],['id'=>30,'language'=>'en-GB','access'=>1],['id'=>40,'language'=>'fr-FR','access'=>2]];
    $routes=\Joomla\Component\Comarquage\Site\Service\RouteHelper::class;
    check($routes::selectMenuItem($menus,'fr-FR',[1])===20 && $routes::selectMenuItem($menus,'en-GB',[1])===30,'Menu routes respect the current language');
    check($routes::selectMenuItem($menus,'fr-FR',[1],40)===20 && $routes::selectMenuItem($menus,'fr-FR',[1,2],40)===40,'Menu routes respect access and active menu');
    check($routes::selectMenuItem($menus,'de-DE',[1])===10 && $routes::selectMenuItem([$menus[2]],'fr-FR',[1])===0,'All-language fallback never selects a foreign-language menu');
    echo "$count regression checks passed.\n";
}
