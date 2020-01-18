<?php

class dependency_manager
{
    public $workingDir = __DIR__ . "/phars/";
    public $sources;
    public $dependencies = array();
    public $resources = array();
    private $included = array();

    const DEPXML = "dependencies.xml";

    public function __construct($fnames = null, $wdir = null)
    {
// print "\n<br/>dependency_manager::__construct: "; print_r($fnames); print_r($wdir);
        if ($fnames != null) {
            if (is_string($fnames)) $this->sources = array($fnames);
            else if (is_array($fnames)) $this->sources = $fnames;
        }

        if ($wdir != null) $this->workingDir = $wdir;
        if (substr($this->workingDir, -1) != "/") $this->workingDir .= "/";

//  print    "\n<br/>Initializing..";
        $this->ensure_config();
        $this->load_internal_resources();
        $this->include_autoloads();
//  print "\n<br/>Loading sources..";
        $this->load_sources();
//  print "\n<br/>Loaded sources..";
        $this->include_autoloads();
//  print "\n<br/>Loaded autoloads..";
    }

    protected function ensure_config() 
    {
// print "\n<br/>Ensuring Config..";
// print "\n<br/>workingDir=$this->workingDir";
        if (!file_exists($this->workingDir)) 
            @mkdir($this->workingDir, 0777);
        if (!file_exists($this->workingDir)) throw new Exception("Cannot secure working folder: $this->workingDir");

        if ($this->sources == null) array($this->default_source());

        foreach($this->sources as $source)
            if (!file_exists($source)) throw new Exception("Cannot locate source: $source");
    }

    protected function load_internal_resources()
    {
// print "\n<br/>Loading Internal..";
        $internal_resources = array(
            "github://bhoogter:xml-file/phar:0.2.59",
        );

        foreach ($internal_resources as $resource) 
            $this->load_resource_string($resource);
    }

    public function default_source()
    {
        if (file_exists($v = ($this->workingDir . "/" . dependency_manager::DEPXML))) return $v;
        if (file_exists($v = (__DIR__ . "/" . dependency_manager::DEPXML))) return $v;
        $d = realpath(__DIR__);
        while (strlen($d) >= strlen($_SERVER["DOCUMENT_ROOT"])) {
            if ($d == ".") break;
            $dd = dirname($d);
            if ($dd == $d) break;
            $d = $dd;
//print ("\nd=$d");
            if (file_exists($v = ("$d/" . dependency_manager::DEPXML))) return $v;
        }
        return __DIR__ . "/" . dependency_manager::DEPXML;
    }

    public function load_sources()
    {
        $this->dependencies = array();
// print "\n<br/>load_sources()";
        foreach ($this->sources as $source)
        {
// print "\n<br/>load_sources(), loading source=$source";
            $this->dependencies[] = new xml_file($source);
        }
// print "\n<br/>load_sources(), ensuring dependencies...";
            $this->ensure_dependencies();
    }

    public function ensure_dependencies()
    {
        foreach ($this->dependencies as $dependency) {
            $deps = $dependency->lst("//*/dependency/@name");
// print ("\n<br/>DEPS="); print_r($deps);
            foreach ($deps as $dName) {
                $dGrps = $dependency->get("//*/dependency[@name='$dName']/@group");
                $dVers = $dependency->get("//*/dependency[@name='$dName']/@version");
                $dType = $dependency->get("//*/dependency[@name='$dName']/@type");
                $dUrls = $dependency->get("//*/dependency[@name='$dName']/@url");

                if ($dType == null) $dType = "phar";
                $resourceFile = $this->get_git($dGrps, $dName, $dVers, $dType);
                $this->process_dependency($resourceFile, $dType, $dName);
            }
        }
// print("\n======================\n");
// print_r($this->resources);
// print("\n======================\n");
    }

    public function load_resource_string($str)
    {
        $nam = '';
        $typ = '';
        $resourceFile = "";
        if ("github://" == substr($str, 0, 9))
            {
                $gitstr = substr($str, 9);
                $parts = explode(":", $gitstr);
                $grp = sizeof($parts) > 0 ? $parts[0] : "";
                $nam = sizeof($parts) > 1 ? $parts[1] : "";
                $ver = sizeof($parts) > 2 ? $parts[2] : "";
                $namParts = explode('/', $nam);
                $typ = sizeof($namParts) > 1 ? $namParts[1] : 'phar';
                $nam = sizeof($namParts) > 1 ? $namParts[0] : '';
// print "\n<br/>LOAD RESOURCE STRING PARTS: nam=$nam, grp=$grp, typ=$typ, ver=$ver";

                $resourceFile = $this->get_git($grp, $nam, $ver, $typ);
            }
        $this->process_dependency($resourceFile, $typ, $nam);
        return $resourceFile;
    }

    public function get_git($grp, $nam, $ver, $typ = 'phar', $url = '')
    {
        $resourceFile = $this->local_file_name($grp, $nam, $ver, $typ);
        if ($url == null) $url = "https://github.com/$grp/$nam/releases/download/$ver/$nam.$typ";
        if (!file_exists($resourceFile)) $this->fetch_dependency($url, $resourceFile);
        return $resourceFile;
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

    public function local_file_name($group, $name, $version, $type)
    {
// print "\n<br/>local_file_name(), workingDir = $this->workingDir";
        $result = $this->slugify($group) . "-" . $this->slugify($name) . '-' . $this->slugify($version) . "." . $type;
        while (strpos($result, "--") !== false) $result = str_replace("--", "-", $result);

        $result = $this->workingDir . $result;
        $result = str_replace('/', '\\', $result);

// print "\n<br/>local_file_name(): $result";
        return $result;
    }

    public function fetch_dependency($url, $local_file)
    {
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
                throw new Excpetion("Error requiring dependency: $url - $err");
                // print("<br/>ERROR REQURING DEPENDENCY: $url - $err");
                // die();
            }
// print("\n<br/>local_file=$local_file");
            file_put_contents($local_file, $result);
        }

        return file_exists($local_file);
    }

    public function process_dependency($resourceFile, $type, $name) {
        switch($type)
        {
            case "phar":
                $this->scan_phar_files($resourceFile, $name);
                break;
        }
    }

    public function scan_phar_files($phar_file, $name)
    {
//  print("\n<br/>Reading PHAR: $phar_file\n");
        $phar = new Phar($phar_file, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $name);
// print("\n<br/>Requiring PHAR: $phar_file");
        include_once($phar_file);
        $basepath = "phar://" . $phar->getPath() . "/";
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $filename = str_replace($basepath, "", $file->getPath() . '/' . $file->getFilename());
            // print_r("\n$basepath");
            //  print_r("\nfilename=$filename");
            $this->resources[$filename] = $name;
        }
    }

    public function include($fname)
    {
//  print("\n<br/>Searching for: [$fname]");
        foreach ($this->resources as $file => $pharAlias) {
// print("\n<br/>Searching for: [$fname] in [$pharAlias]: $file");
            $found = false;
            if (strpos($file, $fname) !== false) $found = true;
            if (strpos($file, $k = str_replace("_", "-", $fname)) !== false) $found = true;

            if ($found) {

                $src = "phar://$pharAlias/$file";
// print("\n<br/>file=$file, src=$src");
                if (in_array($src, $this->included)) {
// print("\n<br/>file=$file, src=$src SKIPPED");
                    continue;
                }
                require_once($src);
                $this->included[] = $src;
            }
        }
    }

    public function include_once($fname)    {   return $this->include($fname);     }
    public function require($fname)         {   return $this->include($fname);     }
    public function require_once($fname)    {   return $this->include($fname);     }

    public function include_autoloads()     {   $this->include("autoload.php");    }
}

if (!function_exists("dependency_manager")) {
    function dependency_manager($scope = "default", $vsources = null, $vworkspace = null, $autoload = null)
    {
// print "\n<br/>dependency_manager($scope): "; print "\nvsources="; print_r($vsources); print("\nworkspace="); print_r($vworkspace);
        static $o;
        if ($o == null) $o = array();

        if ($autoload != null) {
            foreach ($o as $dp) $dp->include($autoload);
            return;
        }

        if (!array_key_exists($scope, $o) || $vsources != null || $vworkspace != null)
            @$o[$scope] = new dependency_manager($vsources, $vworkspace);
        return $o[$scope];
    }
}

spl_autoload_register(function ($name) {
    dependency_manager(null, null, null, $name);
});
