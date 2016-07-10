<?php

require_once('Bot.php');
require_once(__DIR__.'/../../vendor/autoload.php');

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * HTMLtoImage Bot class
 */
abstract class HTMLtoImageBot extends Bot {
    protected function _convertAndPost($sChatId, $sHTML) {
        $sTempFile = tempnam(sys_get_temp_dir(), 'webpage');

        $oOptions = new Options();
        $oOptions->setPdfBackend('GD');
        $oOptions->setFontDir(realpath(__DIR__.'/../fonts'));
        $oOptions->setIsRemoteEnabled(true);

        $dompdf = new DOMPDF($oOptions);
        $dompdf->load_html($sHTML, 'UTF-8');

        $dompdf->render();

        file_put_contents($sTempFile.'.png', $dompdf->output(array('compress' => 0)));

        $aRes = $this->sendPhoto($sChatId, $sTempFile.'.png');

        unlink($sTempFile.'.png');

        return $aRes;
    }
}
