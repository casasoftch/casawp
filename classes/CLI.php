<?php

// file: modules/casawp/CLI.php
namespace casawp;

use WP_CLI;

class CLI {

    /**
     * Runs a full CASAWP import (fetch XML, process, cleanup).
     *
     * ## EXAMPLES
     *     wp casawp import
     */
    public function import() {
        $import = new \casawp\Import(false, true);
        $import->updateImportFileThroughCasaGateway();   // this already schedules or runs batches
        WP_CLI::success( 'CASAWP import finished.' );
    }
}
