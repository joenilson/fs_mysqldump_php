<?php

/*
 * Copyright (C) 2017 Joe Nilson <joenilson at gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once 'plugins/fs_mysqldump_php/vendor/Ifsnop/Mysqldump/Mysqldump.php';

use Ifsnop\Mysqldump as IMysqldump;
/**
 * Description of fs_respaldo
 *
 * @author Joe Nilson <joenilson at gmail.com>
 */
class fs_respaldo extends fs_controller{
    public $mysqldump;
    public $archivo;
    public $archivos;
    public $directorio;
    public $exportDir;
    public $documentosDir;
    public $publicPath;
    
    public function __construct() {
        parent::__construct(__CLASS__, 'Respaldos', 'admin', TRUE, TRUE);
    }
    
    protected function private_core() {
        parent::private_core();
        $this->carpetasRespaldo();        
        if(\filter_input(INPUT_POST, 'accion')){
            $this->crearRespaldo();
        }
        $this->archivos = $this->getFiles($this->exportDir);
    }
    
    public function crearRespaldo()
    {
        $fecha = date('Ymd');
        $dumpSettings = array(
            'compress' => IMysqldump\Mysqldump::NONE,
            'no-data' => false,
            'add-drop-table' => true,
            'single-transaction' => true,
            'lock-tables' => true,
            'add-locks' => true,
            'extended-insert' => true,
            'disable-foreign-keys-check' => true,
            'skip-triggers' => false,
            'add-drop-trigger' => true,
            'databases' => true,
            'add-drop-database' => true,
            'hex-blob' => true
        );
        $backup = 'mysqldump_'.FS_DB_NAME.'_'.$fecha.'.sql';
        $dump = new IMysqldump\Mysqldump("mysql:host=".FS_DB_HOST.";dbname=".FS_DB_NAME, FS_DB_USER, FS_DB_PASS, $dumpSettings);
        $dump->start($this->exportDir.DIRECTORY_SEPARATOR.$backup);
        $this->template = false;
        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'mensaje' => 'Backup generado: ' . $backup));
    }
    
    public function carpetasRespaldo()
    {
        $basepath = dirname(dirname(dirname(__DIR__)));
        $this->documentosDir = $basepath . DIRECTORY_SEPARATOR . FS_MYDOCS . 'documentos';
        $this->exportDir = $this->documentosDir . DIRECTORY_SEPARATOR . "backup_mysql";
        $this->publicPath = FS_PATH . FS_MYDOCS . 'documentos' . DIRECTORY_SEPARATOR . 'backup_mysql';
        if (!is_dir($this->documentosDir)) {
            mkdir($this->documentosDir);
        }

        if (!is_dir($this->exportDir)) {
            mkdir($this->exportDir);
        }
    }
    
    private function getFiles($dir) {
        $results = array();
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile()) {
                //verificamos si el archivo ez un zip y si tiene un config.json
                //$informacion = $this->getConfigFromFile($dir, $file);
                $archivo = new stdClass();
                $archivo->filename = $file->getFilename();
                $archivo->path = $file->getPathName();
                // FIXME Revisar para no tener que pasar el valor por duplicado
                $archivo->escaped_path = addslashes($file->getPathName());
                $archivo->size = self::tamano(filesize($file->getPathName()));
                $archivo->date = date('Y-m-d', filemtime($file->getPathName()));
                $archivo->type = $file->getExtension();
                $archivo->file = TRUE;
                //$archivo->conf = $informacion;
                $results[] = $archivo;
            } else {
                continue;
            }
        }
        $ordenable = Array();
        foreach ($results as &$columnaorden) {
            $ordenable[] = &$columnaorden->date;
        }
        array_multisort($ordenable, SORT_DESC, SORT_STRING, $results);
        return $results;
    }

    public function tamano($tamano) {
        /* https://es.wikipedia.org/wiki/Mebibyte */
        $bytes = $tamano;
        $decimals = 2;
        $sz = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $sz[$factor];
    }

}
