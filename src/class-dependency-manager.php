<?php

class dependency_manager
{
    public $workingDir = __DIR__;
    public $sources = array();
    public $dependencies = array();
    public $packages = array();
    public $resources = array();
    private $included = array();

    public const DEPXML = "dependencies.xml";
    
    public static $log_dump = false;
    private static function dump_log($type, ...$msgs) { 
        if (!self::$log_dump) return; 
        $s = "\n$type: ";
        foreach($msgs as $m) $s .= is_string($m) ? $m . " " : print_r($m, true);
        print $s;
    }
    private static function has_logger() {
        static $logger;
        if (!isset($logger)) $logger = class_exists("php_logger");
        return $logger;
    }
    static function trace(...$msgs) { if (self::has_logger()) php_logger::trace(...$msgs); else self::dump_log("TRACE", ...$msgs); }
    static function debug(...$msgs) { if (self::has_logger()) php_logger::debug(...$msgs); else self::dump_log("DEBUG", ...$msgs); }
    static function info(...$msgs) { if (self::has_logger()) php_logger::info(...$msgs); else self::dump_log("INFO", ...$msgs); }
    static function log(...$msgs) { if (self::has_logger()) php_logger::log(...$msgs); else self::dump_log("LOG", ...$msgs); }

    public function clear() {
        $this->workingDir = __DIR__;
        $this->sources = array();
        $this->dependencies = array();
        $this->packages = array();
        $this->resources = array();
        $this->included = array();
    }

    public function __construct($fnames = null, $wdir = null)     {         
        self::trace("DEPENDENCY_MANAGER::__CONSTRUCT: ", $fnames, $wdir);
        $this->clear();
        $this->init($fnames, $wdir);     
    }

    public function init($fnames = null, $wdir = null)
    {
        self::trace("DEPENDENCY_MANAGER::INIT: ", $fnames, $wdir);
        // print "\n";debug_print_backtrace(0, 3);
        if ($fnames != null) {
            if (is_string($fnames)) $fnames = array($fnames);
            else if (!is_array($fnames)) throw new Exception ("Bad argument 1 to dependency_manager().  Expected string or array, got " + gettype($fnames) + ".");
        }
        $this->sources = array_merge($this->sources, $fnames);

        if (is_string($wdir)) $this->workingDir = $wdir;
        if (substr($this->workingDir, -1) != "/") $this->workingDir .= "/";

        foreach(array_keys($fnames) as $fk) $fnames[$fk] = realpath($fnames[$fk]);
        $wdir = realpath($wdir);

        self::debug("INIT: Initializing..");
        $this->ensure_config();
        $this->load_internal_resources();
        $this->include_autoloads();  // for the internal resources, if needed.  Others will follow.
        self::debug("INIT: Loading sources..");
        $this->load_sources();
        self::debug("INIT: Loaded sources..");
        $this->include_autoloads();
        self::debug("INIT: Loaded autoloads..");
    }

    public function internal_resource_list() {
        // Should be maintained here, in this form, as well as dependencies.xml for propagation
        // Cannot use xml_file here, since it is the dependency we are pulling in....  Hence, internal.
        return array(
            "github://bhoogter:xml-file/phar:0.2.75",
            "github://bhoogter:php-logger/phar:1.0.0",
    );
    }

    protected function ensure_config() 
    {
        self::trace("Ensuring Config...", "workingDir=$this->workingDir");
        if (!file_exists($this->workingDir)) 
            @mkdir($this->workingDir, 0777);
        if (!file_exists($this->workingDir)) 
            throw new Exception("Cannot secure working folder: $this->workingDir");

        self::trace("Sources: ", gettype($this->sources));
        if ($this->sources == null) $this->sources = array($this->default_source());
        self::trace("Sources: ", gettype($this->sources));
        self::trace("Sources: ", $this->sources);

        if (is_array($this->sources))
            foreach($this->sources as $source) {
                if (!!($t = realpath($source))) $source = $t;
                if (!file_exists($source)) throw new Exception("Cannot locate source: " . $source);
            }
    }

    protected function load_internal_resources()
    {
        self::debug("php-dependency-manager::load_internal_resources, list=", $this->internal_resource_list());
        foreach ($this->internal_resource_list() as $resource) 
            $this->load_resource_string($resource);
    }

    public function default_source()
    {
        $phar = strpos(__FILE__, ".phar") !== false;
        self::debug("dependency_manager::default_source, phar=$phar");

        if (!$phar) {
            if (file_exists($v = (dirname(__DIR__) . "/" . self::DEPXML))) return $v;
            if (file_exists($v = (__DIR__ . "/" . self::DEPXML))) return $v;
            if (file_exists($v = ($this->workingDir . "/" . self::DEPXML))) return $v;
        }

        $D = [$this->workingDir, realpath(dirname(__DIR__)), realpath(__DIR__)];
        foreach($D as $d) {
            while (strlen($d) >= strlen($_SERVER["DOCUMENT_ROOT"])) {
                self::trace("SCAN: default_source -  d=$d");
                if ($d == ".") break;
                $dd = dirname($d);
                if ($dd == $d) break;
                $d = $dd;
                if (file_exists($v = ("$d/" . self::DEPXML))) return $v;
            }
        }

        $result = __DIR__ . "/" . self::DEPXML;
        self::debug("dependency_manager::default_source: " . $result);
        return $result;
    }

    public function load_sources()
    {
        $this->dependencies = array();
        self::trace("load_sources()"); 
        if (is_null($this->sources)) $this->sources = array();
        if (!is_array($this->sources)) throw new Exception("Sources is not an array.");
        $this->sources = array_unique($this->sources);
        while (count($to_load = array_diff($this->sources, array_keys($this->dependencies))) > 0) {
            self::log("LOAD_SOURCES TO LOAD: ", $to_load);
            foreach ($to_load as $source) {
                $source = $source;
                self::info("load_sources(), loading source=$source");
                $this->dependencies[$source] = new xml_file($source);
            }
// print "\n====> ";
// print_r(array_diff($this->sources, $sources_loaded));
            $this->ensure_dependencies();
        }
        self::trace("load_sources(), ensuring dependencies...");
        $this->ensure_dependencies();
    }

    public function ensure_dependencies()
    {
        self::log("ensure_dependencies()");
        foreach ($this->dependencies as $sourceFile => $dependency) {
            $deps = $dependency->lst("//*/dependency/@name");
            self::log("DEPS=", $deps);
            foreach ($deps as $dName) {
                $dRepo = $dependency->get("//*/dependency[@name='$dName']/@repository");
                $dGrps = $dependency->get("//*/dependency[@name='$dName']/@group");
                $dVers = $dependency->get("//*/dependency[@name='$dName']/@version");
                $dType = $dependency->get("//*/dependency[@name='$dName']/@type");
                $dUrls = $dependency->get("//*/dependency[@name='$dName']/@url");

                $rs = self::build_resource_string($dRepo, $dGrps, $dName, $dVers, $dType, $dUrls);
                self::log("Loading resource string [$dName@$dRepo]: $rs");
                $this->load_resource_string($rs);
            }
        }
    // self::trace("\n======================\n", $this->resources, "\n======================\n");
    }

    public static function build_resource_string($repo, $group, $name, $version, $type = null, $url = null) {
        if ($repo == null || $repo == "") $repo = "github";
        if ($type == "") $type = null;
        if ($url == "") $url = null;
        $types = ($type == null ? "" : "/$type");
        $urls = ($url == null ? "" : ":$url");
        switch($repo) {
            case "github": return "github://$group:$name$types:$version$urls";
            default: return null;
        }
    }

    public static function parse_resource_string($rs) {
        $opts = [];

        if ("github://" == substr($rs, 0, 9)) {
            $opts['repo'] = 'github';
            $rs = substr($rs, 9);
        } else {
            $opts['repo'] = '';
        }

        $parts = explode(":", $rs, 4);
        $opts['group'] = sizeof($parts) > 0 ? $parts[0] : "";
        $opts['name'] = sizeof($parts) > 1 ? $parts[1] : "";
        $opts['version'] = sizeof($parts) > 2 ? $parts[2] : "";
        $opts['url'] = sizeof($parts) > 3 ? $parts[3] : "";

        $namParts = explode('/', $opts['name']);
        $opts['type'] = sizeof($namParts) > 1 ? $namParts[1] : 'phar';
        $opts['name'] = sizeof($namParts) > 0 ? $namParts[0] : '';

        $verParts = explode('@', $opts['version']);
        $opts['ext'] = sizeof($verParts) > 1 ? $verParts[1] : '';
        $opts['version'] = sizeof($verParts) > 0 ? $verParts[0] : '';

        self::trace("LOAD RESOURCE STRING PARTS: rep=${opts['repo']}, nam=${opts['name']}, grp=${opts['group']}, typ=${opts['type']}, ver=${opts['version']}");
        return $opts;
    }

    public function load_resource_string($str)
    {
        self::trace("load_resource_string: $str");
        $resourceFile = "";

        $opts = self::parse_resource_string($str);
        self::log("Checking " . $opts['name']);
        if (false != array_key_exists($opts['name'], $this->packages)) {
            self::log("Skipping already loaded package: " . $opts['name']);
            return $this->packages[$opts['name']];
        }

        switch($opts['repo']) {
            case "github": 
                $resourceFile = $this->get_git($opts['group'], $opts['name'], $opts['version'], $opts['type'], $opts['url']);
                break;  
            default:
                $resourceFile = null;
        }

        if ($resourceFile != null) {
            $this->process_dependency($resourceFile, $opts['type'], $opts['name']);
            $this->packages[$opts['name']] = $resourceFile;
        }
        return $resourceFile;
    }

    public function get_git($grp, $nam, $ver, $typ = 'phar', $url = '')
    {
        self::debug("source::get_git($grp, $nam, $ver, $typ, $url)");
        if ($this->dynmaicVersioning()) $ver = $this->resolveGitVersion($grp, $nam, $ver, $url);
        $resourceFile = $this->local_file_name($grp, $nam, $ver, $typ);
        self::debug("source::get_git: resourceFile=$resourceFile");
        if (!file_exists($resourceFile)) {
            if ($url == null) $url = "https://github.com/$grp/$nam/releases/download/$ver/$nam.$typ";
            self::debug("source::get_git: url=$url");
            $this->fetch_dependency($url, $resourceFile);
        }
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
        self::debug("local_file_name(), workingDir = $this->workingDir");
        $result = $this->slugify($group) . "-" . $this->slugify($name) . '-' . $this->slugify($version) . "." . $type;
        while (strpos($result, "--") !== false) $result = str_replace("--", "-", $result);

        $result = $this->workingDir . $result;
        $result = str_replace('/', '\\', $result);

        self::debug("local_file_name(): $result");
        return $result;
    }

    public function fetch_dependency($url, $local_file)
    {
        self::debug("php-dependency-manager::fetch_dependency(url=$url, local_file=$local_file)");
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

            // self::trace("\n================\n$result\n================\n");
            if ($result === false || $result == "") {
                throw new Exception("Error requiring dependency: $url - $err");
                // print("<br/>ERROR REQURING DEPENDENCY: $url - $err");
                // die();
            }
            self::trace("local_file=$local_file");
            file_put_contents($local_file, $result);
        }

        return file_exists($local_file);
    }

    public function process_dependency($resourceFile, $type, $name) {
        self::log("Processing dependency: $name");
        switch($type)
        {
            case "phar":
                $this->scan_phar_file($resourceFile, $name);
                break;
        }
    }

    public function scan_phar_file($phar_file, $name)
    {
        self::debug("Scanning Phar File: $phar_file");
        $phar = new Phar($phar_file, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $name);
        self::trace("Requiring PHAR: $phar_file");
        require_once($phar_file);
        $basepath = "phar://" . $phar->getPath() . "/";
        self::trace("Basepath=$basepath");
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $filename = str_replace($basepath, "", $file->getPath() . '/' . $file->getFilename());
            self::trace("SCAN filename=$filename");
            $this->resources[$filename] = $name;
            if (substr_compare($filename, self::DEPXML, -strlen(self::DEPXML)) === 0) {
                $add = "phar://$name/$filename";  // Load via PHAR alias (path in travis is unreliable)
                self::trace("Found module dependencies: " . $file->getPath() . '/' . $file->getFilename() . ", adding: $add");
                $this->sources[] = $add;
            }
        }
    }

    public function include($fname)
    {
        self::debug("include: Searching for: [$fname]");
        self::trace($this->resources);
        foreach ($this->resources as $file => $pharAlias) {
            self::debug("Searching for: [$fname] in [$pharAlias]: $file");
            $found = false;
            if (strpos($file, $fname) !== false) $found = true;
            if (strpos($file, $k = str_replace("_", "-", $fname)) !== false) $found = true;

            // self::debug("Searching for: [$fname] in [$pharAlias]: (" . ($found?'found':'not found') . ") $file");
            if ($found) {
                $src = "phar://$pharAlias/$file";
                self::debug("Searching for: [$fname] in [$pharAlias]: **FOUND** $file");
                if (in_array($src, $this->included)) {
                    self::debug("file=$file, src=$src SKIPPED");
                    continue;
                }
                self::debug("Requiring: $src");
                require_once($src);
                $this->included[] = $src;
            }
        }
        self::debug("\n<br/>Searching for: [$fname], NOT FOUND");
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
    function dependency_manager($vsources = null, $vworkspace = null, $autoload = null)
    {
        static $depmgr;
        
        if ($autoload != null) {
            // print "\nAUTOLOAD=$autoload";
            if (isset($depmgr)) $depmgr->include($autoload);
            return;
        }

        dependency_manager::info("DEPENDENCY_MANAGER: ", "vsources=", $vsources, ", workspace=", $vworkspace, ", AL=", $autoload);
        if (!isset($depmgr)) {
            dependency_manager::info("dependency_manager - INITIALIZING CLONE");
            $depmgr = new dependency_manager($vsources, $vworkspace);
        } else if ($vsources != null || $vworkspace != null) {
            // print "\n------------\n"; debug_print_backtrace();
            dependency_manager::info("dependency_manager - INITIALIZING ADDING");
            $depmgr->init($vsources, $vworkspace);
        }

        return $depmgr;
    }
}

spl_autoload_register(function ($name) {
    dependency_manager(null, null, $name);
});
