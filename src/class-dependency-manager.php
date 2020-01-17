<?php

class dependency_manager
{
    public $workingDir = __DIR__;
    public $sources;
    public $dependencies = array();
    public $resources = array();

    const DEPXML = "dependencies.xml";

    public function __construct($fnames = null, $wdir = null)
    {
        if ($fnames != null) 
        {
            if (is_string($fnames)) $this->sources = array($fnames);
            else if (is_array($fnames)) $this->sources = $fnames;
        }
        if ($this->sources == null) array($this->default_source());

        if ($wdir != null) $this->workingDir = $wdir;
        if (substr($this->workingDir, -1) != "/") $this->workingDir .= "/";


// print "\n<br/>Loading sources..";
        $this->load_sources();
// print "\n<br/>Loaded sources..";
        $this->include_autoloads();
// print "\n<br/>Loaded autoloads..";
}

    public function default_source() {
        if (file_exists( $v = ($this->workingDir . "/" . dependency_manager::DEPXML))) return $v;
        if (file_exists( $v = (__DIR__ . "/" . dependency_manager::DEPXML))) return $v;
        $d = realpath(__DIR__);
        while (strlen($d) >= strlen($_SERVER["DOCUMENT_ROOT"])) {
            if ($d == ".") break;
            $dd = dirname($d);
            if ($dd == $d) break;
            $d = $dd;
//print ("\nd=$d");
            if (file_exists( $v = ("$d/" . dependency_manager::DEPXML))) return $v;
        }
        return __DIR__ . "/" . dependency_manager::DEPXML;

    }

    public function load_sources() {
        require_once("phar://" . __DIR__ . "/xml-file.phar/src/class-xml-file.php");
        $this->dependencies = array();
        if (is_array($this->sources))
            foreach($this->sources as $source) {
                $this->dependencies[] = new xml_file($source);
            }
        $this->ensure_dependencies();
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

                $resourceFile = $this->local_file_name($dGrps, $dName, $dVers, $dType);
                if (!file_exists($resourceFile))
                    $this->fetch_dependency($dUrls, $resourceFile);

// print("\n<br/>type=$dType, file=$resourceFile");
                switch ($dType) {
                    case "phar": $this->scan_phar_files($resourceFile, $dName); break;
                }
            }
        }
// print("\n======================\n");
// print_r($this->resources);
// print("\n======================\n");
    }

    public function scan_phar_files($phar_file, $name)
    {
// print("\n<br/>Reading PHAR: $phar_file");
        $phar = new Phar($phar_file, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $name);
// print("\n<br/>Requiring PHAR: $phar_file");
        // require($phar_file);
        $basepath = "phar://" . $phar->getPath() . "/";
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $filename = str_replace($basepath, "", $file->getPath() . '/' . $file->getFilename());
// print_r("\n$basepath");
//  print_r("\nfilename=$filename");
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

    public function local_file_name($group, $name, $version, $type) {
        $result = $this->slugify($group) . "-" . $this->slugify($name) . '-' . $this->slugify($version) . "." . $type;
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

    public function include($fname)
    {
        // print("\n<br/>Searching for: [$fname]");
        foreach ($this->resources as $file => $pharAlias) {
            $found = false;
            if (strpos($file, $fname) !== false) $found = true;
            if (strpos($file, $k = str_replace("_", "-", $fname)) !== false) $found = true;

            if ($found) {
                $src = "phar://$pharAlias/$file";
// print("\n<br/>file=$file, src=$src");
                require_once($src);
            }
        }
    }

    public function include_once($fname) { return $this->include($fname); }
    public function require($fname) { return $this->include($fname); }
    public function require_once($fname) { return $this->include($fname); }

    public function include_autoloads() { $this->include("autoload.php"); }
}

if (!function_exists("dependency_manager")) {
    function dependency_manager($scope = "default", $vsources = null, $vworkspace = null, $autoload = null) {
        static $o;
        if ($o == null) $o = array();

        if ($autoload != null) {
            foreach($o as $dp) {
                $dp->include($autoload);
            }
            return;
        }

        if (!array_key_exists($scope, $o) || $vsources != null || $vworkspace != null) 
            $o[$scope] = new dependency_manager($vsources, $vworkspace);
        return $o[$scope];
    }
}

spl_autoload_register(function ($name) { dependency_manager(null, null, null, $name); });
