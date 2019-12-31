<?php

class dependency_manager
{
    public $workingDir = __DIR__;
    public $sources = array(__DIR__ . "dependencies.xml");
    public $dependencies = array();
    public $resources = array();

    public function __construct($fnames = null, $wdir = null)
    {
        if ($fnames != null) 
        {
            if (is_string($fnames)) $this->sources = array($fnames);
            else if (is_array($fnames)) $this->sources = $fnames;
        }
        if ($wdir != null) $this->workingDir = $wdir;
        if (substr($this->workingDir, -1) != "/") $this->workingDir .= "/";

        $this->load_sources();
        $this->ensure_dependencies();
    }

    public function load_sources() {
        require_once("phar://" . __DIR__ . "/xml-file.phar/src/class-xml-file.php");
        $this->dependencies = array();
        foreach($this->sources as $source) {
            $this->dependencies[] = new xml_file($source);
        }
    }

    public function ensure_dependencies()
    {
        foreach ($this->dependencies as $dependency) {
            $deps = $dependency->lst("//*/dependency/@name");
// print ("\n<br/>DEPS=");
// print_r($deps);
            foreach ($deps as $dName) {
                $dGrps = $dependency->get("//*/dependency[@name='$dName']/@group");
                $dVers = $dependency->get("//*/dependency[@name='$dName']/@version");
                $dType = $dependency->get("//*/dependency[@name='$dName']/@type");
                $dUrls = $dependency->get("//*/dependency[@name='$dName']/@url");

                if ($dType == null) $dType = "phar";
                if ($dUrls == null) $dUrls = "https://github.com/$dGrps/$dName/releases/download/$dVers/$dName.phar";
// print("\n<br />nam=$dName, grp=$dGrps, ver=$dVers, typ=$dType, url=$dUrls");

                $resourceFile = $this->local_file_name($dName, $dVers, $dType);
                if (!file_exists($resourceFile))
                    $this->fetch_dependency($dUrls, $resourceFile);

// print("\n<br/>type=$dType, file=$resourceFile");
                switch ($dType) {
                    case "phar": $this->scan_phar_files($resourceFile, $dName); break;
                }
            }
        }
print("\n======================\n");
print_r($this->resources);
print("\n======================\n");
    }

    public function scan_phar_files($phar, $name)
    {
// print("\n<br/>Reading PHAR: $phar");
        $phar = new Phar($phar, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $name);
// print("\n<br/>Requiring PHAR: $phar");
       require($phar);
        $basepath = "phar://" . $phar->getPath();
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $filename = str_replace($basepath, "", $file->getFilename());
// print_r("\n$basepath");
// print_r("\n$filename");
            $this->resources[$filename] = $name;
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
        $result = str_replace('/', '\\', $result);

        return $result;
    }

    public function fetch_dependency($url, $local_file) {
        if (function_exists("curl_init")) {
            $ch = curl_init(); 
// print("\nurl=$url");
            curl_setopt($ch, CURLOPT_URL, $url); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            // curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($ch); 
            $err = curl_error($ch);
            curl_close($ch);

// print("\n================");
// print("\n$result");
// print("\n================");
            if ($result === false || $result == "") {
                print("<br/>ERROR REQURING DEPENDENCY: $url - $err");
                die();
            }
// print("\n<br/>local_file=$local_file");
            file_put_contents($local_file, $result);
        }

        return file_exists($local_file);
    }

    public function include($fname) {
        foreach($this->resources as $file => $pharAlias) {
            if (strpos($file, $fname) !== false) {
                require_once("phar://$pharAlias/$file");
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

if (!function_exists("dependency_manager_autoload")) {
    function dependency_manager_autoload($name) {
        dependency_manager()->include($name);
    }
}

spl_autoload_register('dependency_manager_autoload');
