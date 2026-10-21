<?php
namespace App\Contracts;
/** Design contract only: no auto-loading of uploaded third-party code. */
interface PterohubExtension {
    public function slug(): string;
    public function version(): string;
    public function capabilities(): array;
}
