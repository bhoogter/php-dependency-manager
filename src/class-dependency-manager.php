<?php

require_once("phar://" . __DIR__ . "/class-xml-file.phar/src/class-xml-file.php");

class dependency_manager
{
    public $workingDir = __DIR__;
    public $dependencyFile = __DIR__ . "dependencies.xml";
    public $dependencies;
    public $resources;

    public function __construct($fname = null, $wdir = null)
    {
        if ($fname != null) $this->dependencyFile = $fname;
        if ($wdir != null) $this->workingDir = $wdir;
        if (substr($this->workingDir, -1) != "/") $this->workingDir .= "/";
        $this->dependencies = new xml_file($fname);
        $this->ensure_dependencies();
    }

    public function ensure_dependencies() {
        $deps = $this->dependencies->lst("//*/dependency/@name");
        foreach ($deps as $dName) {
            $dGrps = $this->dependencies->get("//*/dependency[@name='$dName']/group");
            $dVers = $this->dependencies->get("//*/dependency[@name='$dName']/version");
            $dType = $this->dependencies->get("//*/dependency[@name='$dName']/type");
            $dUrls = $this->dependencies->get("//*/dependency[@name='$dName']/url");
            if ($dType == null) $dType = "phar";
            if ($dUrls == null) $dType = "https://github.com/$dGrps/$dName/releases/download/$dVers/$dName.phar";

            $resourceFile = $this->local_file_name($dName, $dVers, $dType);
            if (!file_exists($resourceFile))
            {
                $this->fetch_dependency($dUrls, $resourceFile);
                switch($dType) {
                    case "phar": $this->scan_phar_files($resourceFile);
                }
            }
        }
print("\n======================\n");
print_r($this->resources);
print("\n======================\n");
    }

    public function scan_phar_files($phar)
    {
        $phar = new Phar($phar, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME);
        require($phar);
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $this->resources[$file] = $phar;
        }
    }

    public static function slugify($text)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if (empty($text)) return '';
        return $text;
    }

    public function local_file_name($name, $version, $type) {
        $result = $this->slugify($name) . '-' . $this->slugify($version) . "." . $type;
        while (strpos($result, "--") !== false) $result = str_replace("--", "-", $result);

        $result = $this->workingDir . $result;

        return $result;
    }

    public function fetch_dependency($url, $local_file) {
        if (function_exists("curl_init")) {
            $ch = curl_init(); 
            curl_setopt($ch, CURLOPT_URL, $url); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            $dependency = curl_exec($ch); 
            curl_close($ch);

            file_put_contents($local_file, $dependency);
        }

        return file_exists($local_file);
    }

    public function include($fname) {
        foreach($this->resources as $file => $phar) {
            if (strpos($file, $fname) !== false) {
                require_once("phar://$phar/$file");
            }
        }
    }

    public function include_once($fname) { return $this->include($fname); }
    public function require($fname) { return $this->include($fname); }
    public function require_once($fname) { return $this->include($fname); }
}

if (!function_exists("dependency_manager")) {
    function dependency_manager() {
        static $o;
        if ($o == null) $o = new dependency_manager();
        return $o;
    }
}