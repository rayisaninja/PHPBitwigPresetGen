<?php
require_once 'vendor/autoload.php';
require_once 'includes/bitwig.inc.php';

use splitbrain\PHPArchive\Zip;

$inifile = "bitwig.ini";
if (isset($argv[1])) {
    $inifile = $argv[1];
}

//
$ini = parse_ini_file($inifile, true, INI_SCANNER_RAW);

foreach ($ini['General'] as $k => $instrument) {
    //step through all instrument definitions
    if (preg_match('~^instrument[0-9]+$~i', $k)) {
        if (isset($ini[$instrument])) {
            echo "VST " . $instrument . "\n";
            $config = $ini[$instrument];
            $presetfileext = 'fxp';

            $ignoreregex = array();
            foreach ($config as $k => $v) {
                if (preg_match('~^ignoreregex[0-9]*$~i', $k)) {
                    $ignoreregex[] = $v;
                }
            }

            foreach ($config as $k => $path) {
                if (preg_match('~^presetfolder[0-9]*$~i', $k)) {
                    $path = explode('\\*.', $path);

                    //file extension from path
                    if (count($path) > 1) {
                        $presetfileext = $path[1];
                        $path = $path[0];
                    } else {
                        $path = $path[0];
                    }

                    echo "-> searching for presets in: " . $path . "\n";
                    echo "Preset file extension: " . $presetfileext . "\n";

                    $files = bitwigFetchAllFiles($path, $presetfileext);
                    echo "-> " . count($files) . " presets found\n";
                    foreach ($files as $k => $v) {
                        $preset = new bitwigPreset();
                        $preset->file = $v;
                        $preset->name = basename($preset->file, '.' . $presetfileext);
                        foreach ($ignoreregex as $regex) {
                            if (preg_match($regex, $v)) {
                                echo "-> preset " . $preset->name . " will be ignored!\n";
                                continue 2;
                            }
                        }
                        $zip = new Zip();
                        $zip->create();
                        $zip->setCompression(0);
                        $preset->vstid = $config['vstid'];
                        $preset->vstversion = $config['vstversion'];
                        $preset->creator = '';
                        $preset->device_creator = $config['devicecreator'];
                        $preset->preset_category = '';
                        $preset->device_name = $config['vstname'];
                        $preset->comment = 'Preset generated from ' . $v;
                        if ($presetfileext != 'fxp') {
                            $zip->addData("plugin-states/" . md5($v) . ".fxp", bitwigCreateFXP($preset->vstid, $preset->vstversion, isset($config['vstparametercount']) ? $config['vstparametercount'] : 1, file_get_contents($v)));
                        } else {
                            $zip->addData("plugin-states/" . md5($v) . ".fxp", file_get_contents($v));
                        }
                        $preset->zipcontent = $zip->getArchive();
                        $zip->close();
                        $preset = bitwigDetectMeta($preset, $config);
                        //check if target folder exists
                        $preset_path = $ini['General']['targetfolder'] . '\\' . $instrument . '\\';
                        if ($preset->creator != '') {
                            $preset_path .= basename($preset->creator) . '\\';
                        }
                        if (!file_exists($preset_path)) {
                            mkdir($preset_path, 0777, true);
                        }
                        if (!file_exists($preset_path . $preset->name . '.bwpreset') || (isset($config['overwrite']) && $config['overwrite'] == 1)) {
                            file_put_contents($preset_path . $preset->name . '.bwpreset', bitwigCreatePatch($preset));
                            echo "-> preset " . $preset->name . " saved!\n";
                        } else {
                            /* nothing to do! */
                        }
                    }
                }
            }
        }
    }
}
