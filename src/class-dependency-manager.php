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
//  print "\n<br/>dependency_manager::__construct: "; print_r($fnames); print_r($wdir);
        if ($fnames != null) {
            if (is_string($fnames)) $this->sources = array($fnames);
            else if (is_array($fnames)) $this->sources = $fnames;
        }

        if ($wdir != null) $this->workingDir = $wdir;
        if (substr($this->workingDir, -1) != "/") $this->workingDir .= "/";

//   print    "\n<br/>Initializing..";
        $this->ensure_config();
        $this->load_internal_resources();
        $this->include_autoloads();  // for the internal resources, if needed.  Others will follow.
//   print "\n<br/>Loading sources..";
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

        if (is_array($this->sources))
            foreach($this->sources as $source)
                if (!file_exists($source)) throw new Exception("Cannot locate source: $source");
    }

    protected function internal_resource_list() {
        $d = (strpos(__FILE__, ".phar") === false ? __DIR__ : "phar://" . __FILE__ . "/src");
        $f = $d . "/" . self::DEPXML;
// print "\n<br/>source::internal_resource_list - f=$f";
        $src = file_get_contents($f);
        $src = str_replace('<?xml version="1.0" ?>', "", $src);
// print $src;

        preg_match_all('/name="([^"]*)"/', $src, $names);
        $names = $names[0];
        
        preg_match_all('/group="([^"]*)"/', $src, $groups);
        $groups = $groups[0];

        preg_match_all('/version="([^"]*)"/', $src, $versions);
        $versions = $versions[0];

        $internal_resources = array();
        for($i = 0; $i < count($names); $i++) {
            $ref = "github://";
            $ref .= str_replace(array('"', 'group='), '', $groups[$i]);
            $ref .= ':';
            $ref .= str_replace(array('"', 'name='), '', $names[$i]);
            $ref .= '/phar:';
            $ref .= str_replace(array('"', 'version='), '', $versions[$i]);

            $internal_resources[] = $ref;
        }

// print_r($internal_resources);die();
        return $internal_resources;
    }

    protected function load_internal_resources()
    {
        foreach ($this->internal_resource_list() as $resource) 
            $this->load_resource_string($resource);
    }

    public function default_source()
    {
        if (file_exists($v = ($this->workingDir . "/" . dependency_manager::DEPXML))) return $v;
        if (file_exists($v = (__DIR__ . "/" . self::DEPXML))) return $v;
        $d = realpath(__DIR__);
        while (strlen($d) >= strlen($_SERVER["DOCUMENT_ROOT"])) {
            if ($d == ".") break;
            $dd = dirname($d);
            if ($dd == $d) break;
            $d = $dd;
//print ("\nd=$d");
            if (file_exists($v = ("$d/" . self::DEPXML))) return $v;
        }
        return __DIR__ . "/" . self::DEPXML;
    }

    public function load_sources()
    {
        $sources_loaded = array();
        $this->dependencies = array();
// print "\n<br/>load_sources()"; 
        if (is_null($this->sources)) $this->sources = array();
        if (!is_array($this->sources)) throw new Exception("Sources is not an array.");
        $this->sources = array_unique($this->sources);
        while (count($to_load = array_diff($this->sources, $sources_loaded)) > 0) {
// print_r($to_load);
            foreach ($to_load as $source) {
//  print "\n<br/>load_sources(), loading source=$source";
                $sources_loaded[] = $source;
                $this->dependencies[] = new xml_file($source);
            }
// print "\n====> ";
// print_r(array_diff($this->sources, $sources_loaded));
            $this->ensure_dependencies();
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
// print "\n<br/>load_resource_string: $str";
        $opts = [];
        $resourceFile = "";
        if ("github://" == substr($str, 0, 9))
            {
                $gitstr = substr($str, 9);
                $parts = explode(":", $gitstr);
                $opts['group'] = sizeof($parts) > 0 ? $parts[0] : "";
                $opts['name'] = sizeof($parts) > 1 ? $parts[1] : "";
                $opts['version'] = sizeof($parts) > 2 ? $parts[2] : "";

                $namParts = explode('/', $opts['name']);
                $opts['type'] = sizeof($namParts) > 1 ? $namParts[1] : 'phar';
                $opts['name'] = sizeof($namParts) > 0 ? $namParts[0] : '';

// print "\n<br/>LOAD RESOURCE STRING PARTS: nam=${opts[name]}, grp=${opts[group]}, typ=${opts[type]}, ver=${opts[version]}";

                $resourceFile = $this->get_git($opts['group'], $opts['name'], $opts['version'], $opts['type']);
            }
        $this->process_dependency($resourceFile, $opts['type'], $opts['name']);
        return $resourceFile;
    }

    public function get_git($grp, $nam, $ver, $typ = 'phar', $url = '')
    {
// print "\n<br/>source::get_git($grp, $nam, $ver, $typ, $url)";
        if ($this->dynmaicVersioning()) $ver = $this->resolveGitVersion($grp, $nam, $ver, $url);
        $resourceFile = $this->local_file_name($grp, $nam, $ver, $typ);
        if ($url == null) $url = "https://github.com/$grp/$nam/releases/download/$ver/$nam.$typ";
//  print "\n<br/>source::get_git: url=$url, resourceFile=$resourceFile";
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
// print("\nphp-dependency-manager::fetch_dependency(url=$url, local_file=$local_file)");
        if (function_exists("curl_init")) {
            $ch = curl_init();
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
                throw new Exception("Error requiring dependency: $url - $err");
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
                $this->scan_phar_file($resourceFile, $name);
                break;
        }
    }

    public function scan_phar_file($phar_file, $name)
    {
//  print("\n<br/>Reading PHAR: $phar_file\n");
        $phar = new Phar($phar_file, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $name);
// print("\n<br/>Requiring PHAR: $phar_file");
        include_once($phar_file);
        $basepath = "phar://" . $phar->getPath() . "/";
// print("\nBASEPATH=$basepath");
        foreach (new RecursiveIteratorIterator($phar) as $file) {
// print("\nFILEPATH=". $file->getPath());
            $filename = str_replace($basepath, "", $file->getPath() . '/' . $file->getFilename());
// print("\nscan_phar_file: basepath=$basepath, filename=$filename");
            $this->resources[$filename] = $name;
            if (substr_compare($filename, self::DEPXML, -strlen(self::DEPXML)) === 0) {
                $add = "phar://$name/$filename";  // Load via PHAR alias (path in travis is unreliable)
// print "\n<br/>Found module dependencies: " . $file->getPath() . '/' . $file->getFilename() . ", adding: $add";
                $this->sources[] = $add;
            }
        }
    }

    public function include($fname)
    {
// print("\n<br/>Searching for: [$fname]");
        foreach ($this->resources as $file => $pharAlias) {
// print("\n<br/>Searching for: [$fname] in [$pharAlias]: $file");
            $found = false;
            if (strpos($file, $fname) !== false) $found = true;
            if (strpos($file, $k = str_replace("_", "-", $fname)) !== false) $found = true;

// print("\n<br/>Searching for: [$fname] in [$pharAlias]: (${found?'found':'not found'}) $file");
            if ($found) {
                $src = "phar://$pharAlias/$file";
// print("\n<br/>Searching for: [$fname] in [$pharAlias]: **FOUND** $file");
                if (in_array($src, $this->included)) {
// print("\n<br/>file=$file, src=$src SKIPPED");
                    continue;
                }
                require_once($src);
                $this->included[] = $src;
            }
        }
// print("\n<br/>Searching for: [$fname], NOT FOUND");
    }

    public function include_once($fname)    {   return $this->include($fname);     }
    public function require($fname)         {   return $this->include($fname);     }
    public function require_once($fname)    {   return $this->include($fname);     }

    public function include_autoloads()     {   $this->include("autoload.php");    }

////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public $gitTestResponse = null;
    public $gitAuth = null;

    private function dynmaicVersioning() {
        return $this->gitAuth != null;
    }

    public function gitVersionList($owner, $repo)
    {

        $releaseUrl = "https://api.github.com/repos/$owner/$repo/releases";

        if ($this->gitTestResponse)
            return $this->gitTestResponse;

        if (function_exists("curl_init")) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $releaseUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if ($this->gitAuth != null) curl_setopt($ch, CURLOPT_USERPWD, $this->gitAuth);  
            curl_setopt($ch, CURLOPT_USERAGENT, "bhoogter");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
        } else return null;

        return $result;
    }

    public function gitVersions($owner, $repo)
    {
        $versionList = $this->gitVersionList($owner, $repo);
        $versionsListObj = new xml_file();
        $versionsListObj->loadJson($versionList);
        $versions = $versionsListObj->lst("//tag_name");
        usort($versions, 'version_compare');

        return $versions;
    }

    public function resolveGitVersion($owner, $repo, $match, &$url)
    {
        $versionList = $this->gitVersionList($owner, $repo);
        $versionsListObj = new xml_file();
        $versionsListObj->loadJson($versionList);

        $versions = $versionsListObj->lst("//tag_name");
        usort($versions, 'version_compare');
        $versions = array_reverse($versions);

// print "\nmatch=$match";
        $c = substr($match, 0, 1);
        if ($c == '<' || $c == '>' || $c == '=') {
// print "\nMatching...";
            $op = "";
            while($c == '<' || $c == '>' || $c == '=') {
                $op .= substr($match, 0, 1);
                $match = substr($match, 1);
                $c = substr($match, 0, 1);
            }
// print "\nMATCHER: op=$op, match=$match";

            $versions = array_filter($versions, function($v) use($match, $op) {
                return version_compare($v, $match, $op);
            });
        }

        if (strpos($match, '+') !== false) {
            $match = str_replace($match, '+', '0');
            if ($match == '') $match = "0.0.0";
            $versions = array_filter($versions, function($v) use($match) {
                return version_compare($v, $match, ">=");
            });
        }

        $versions = array_values($versions); // reset indexes

// print_r($versions);
        if (sizeof($versions) <= 0) return null;

        $matched = $versions[0];
// print "\n-------------\n";
// print xml_file::make_tidy_string($versionsListObj->saveXML());
// print "\n-------------\n";
// print_r($versionsListObj->get("//item[tag='$matched']"));
        $url = $versionsListObj->get("//item[tag_name='$matched']/assets/item/browser_download_url");
        return $versions[0];
    }


}

if (!function_exists("dependency_manager")) {
    function dependency_manager($scope = "default", $vsources = null, $vworkspace = null, $autoload = null)
    {
// print "\n<br/>dependency_manager($scope): "; print "\nvsources="; print_r($vsources); print("\nworkspace="); print_r($vworkspace);
        static $o;
        if (!is_string($scope)) $scope = "default";
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
