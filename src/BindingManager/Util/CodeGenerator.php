<?php

declare(strict_types=1);

namespace BindingManager\Util;

class CodeGenerator {

    private int $codeLengthBytes;

    /**
     * @param int<1, max> $codeLengthBytes
     */
    public function __construct(int $codeLengthBytes) {
        $this->codeLengthBytes = $codeLengthBytes;
    }

    public function generate(): string {
        return bin2hex(random_bytes($this->codeLengthBytes));
    }
}
