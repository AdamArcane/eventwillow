<?php

namespace App\Support;

use DebugBar\DataFormatter\DebugBarVarDumper;

class CspDebugBarVarDumper extends DebugBarVarDumper
{
    public function __construct(private readonly string $nonce) {}

    protected function getDumper()
    {
        $dumper = parent::getDumper();
        $dumper->setDumpBoundaries(
            '<pre class=sf-dump id=%s data-indent-pad="%s">',
            '</pre><script nonce="'.htmlspecialchars($this->nonce, ENT_QUOTES, 'UTF-8').'">Sfdump(%s)</script>',
        );

        return $dumper;
    }
}
