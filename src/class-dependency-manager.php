<?php

class dependency_manager
{
    public $workingDir = null;
    public $sources = [];
    public $dependencies = [];
    public $packages = [];
    public $resources = [];
    private $included = [];

    public $proxy;

    public const DEPXML = "dependencies.xml";
    
    public static $log_dump = false;
    private static function dump_log($type, ...$msgs) { 
        if (!self::$log_dump) return false;
        $s = "\n$type: ";
        foreach($msgs as $m) $s .= is_string($m) ? $m . " " : print_r($m, true);
        print $s;
        return true;
    }
    private static function has_logger() {
        static $logger;
        if (!isset($logger)) $logger = class_exists("php_logger");
        return $logger;
    }
    static function trace(...$msgs) { return self::has_logger() ? php_logger::trace(...$msgs) : self::dump_log("TRACE", ...$msgs); }
    static function debug(...$msgs) { return self::has_logger() ? php_logger::debug(...$msgs) : self::dump_log("DEBUG", ...$msgs); }
    static function info(...$msgs) { return self::has_logger() ? php_logger::info(...$msgs) : self::dump_log("INFO", ...$msgs); }
    static function log(...$msgs) { return self::has_logger() ? php_logger::log(...$msgs) : self::dump_log("LOG", ...$msgs); }

    public function clear() {
        $this->workingDir = null;
        $this->sources = [];
        $this->dependencies = [];
        $this->packages = [];
        $this->resources = [];
        $this->included = [];
        unset($this->proxy);
    }

    public function __destruct()
    {
        self::trace("DEPENDENCY_MANAGER::__DESTRUCT: ");
    }

    public function __construct($fnames = null, $wdir = null)     {         
        self::trace("DEPENDENCY_MANAGER::__CONSTRUCT: ", $fnames, $wdir);
        $this->clear();
        $this->init($fnames, $wdir);     
    }

    public function init($fnames = null, $wdir = null)
    {
        self::trace("DEPENDENCY_MANAGER::INIT: ", $fnames, $wdir);
        if ($fnames != null) {
            if (is_string($fnames)) $fnames = array($fnames);
            else if (!is_array($fnames)) throw new Exception ("Bad argument 1 to dependency_manager().  Expected string or array, got " + gettype($fnames) + ".");
        }
        $this->sources = array_merge($this->sources, $fnames);

        if (is_string($wdir)) {
            $wdir = realpath($wdir);
            $wdir = [ '' => $wdir ];
        } else if (!is_array($wdir) || count($wdir) == 0) {
            $wdir = [ '' => __DIR__ ];
        }

        $cb = function($v) use ($wdir) { 
            $x = realpath($v); 
            if (false === $x) $x = realpath(dirname($v)) . DIRECTORY_SEPARATOR . basename($v);
            return $x;
        };
        $wdir = array_map($cb, $wdir);
        $wdir = array_filter($wdir, "is_string");

        if (count($wdir) == 0) $wdir = [ '' => __DIR__];
        else if (!isset($wdir[''])) $wdir[''] = array_values($wdir)[0];

        $this->workingDir = $wdir;
        self::debug("Working dirs: ", $this->workingDir);
        // die(print_r($wdir, true));

        foreach($fnames as $fk=>$v) {
            $t = realpath($v);
            if (false === $t) throw new Exception("Cannot load source: $v");
            $fnames[$fk] = $t;
        }

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
        $deplist = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . self::DEPXML);
        $deps = explode("\n", $deplist);
        self::log($deps);
        $result = [];
        foreach($deps as $d) {
            $m = [];
            $pat = "/[<]dependency( group=['\"]([^'\"]*)['\"])?( name=['\"]([^'\"]*)['\"])?( version=['\"]([^'\"]*)['\"])?/i";
            if (!preg_match($pat, $d, $m)) continue;
            $str = $this->build_resource_string("github", $m[2], $m[4], $m[6]);
            $result[] = $str;
        }

        return $result;
    }

    protected function ensure_config() 
    {
        self::trace("Ensuring Config...", "workingDir=", $this->workingDir);
        foreach($this->workingDir as $dir) {
// print "\ndir=$dir";
            if (!file_exists($dir)) mkdir($dir, 0777);
            if (!file_exists($dir)) throw new Exception("Cannot secure working folder: $dir");
        }

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
        $is_phar = strpos(__FILE__, ".phar") !== false;
        self::debug("dependency_manager::default_source, phar=$is_phar");

        if (!$is_phar) {
            if (file_exists($v = (dirname(__DIR__) . DIRECTORY_SEPARATOR . self::DEPXML))) return $v;
            if (file_exists($v = (__DIR__ . DIRECTORY_SEPARATOR . self::DEPXML))) return $v;
            if (file_exists($v = ($this->workingDir[''] . DIRECTORY_SEPARATOR . self::DEPXML))) return $v;
        }

        $D = [$this->workingDir[''], realpath(dirname(__DIR__)), realpath(__DIR__)];
        foreach($D as $d) {
            while (strlen($d) >= strlen($_SERVER["DOCUMENT_ROOT"])) {
                self::trace("SCAN: default_source -  d=$d");
                if ($d == ".") break;
                $dd = dirname($d);
                if ($dd == $d) break;
                $d = $dd;
                if (file_exists($v = ("$d" . DIRECTORY_SEPARATOR . self::DEPXML))) return $v;
            }
        }

        $result = __DIR__ . DIRECTORY_SEPARATOR . self::DEPXML;
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
                $dAltl = $dependency->get("//*/dependency[@name='$dName']/@alt");

                $rs = self::build_resource_string($dRepo, $dGrps, $dName, $dVers, $dType, $dUrls);
                self::log("Loading resource string [$dName@$dRepo]: $rs");
                $this->load_resource_string($rs, $dAltl);
            }
        }
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

    public function load_resource_string($str, $alt = '')
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
                $resourceFile = $this->get_git($opts['group'], $opts['name'], $opts['version'], $opts['type'], $opts['url'], $alt);
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

    public function get_git($grp, $nam, $ver, $typ = 'phar', $url = '', $alt = '')
    {
        self::debug("source::get_git($grp, $nam, $ver, $typ, $url)");
        if ($this->dynmaicVersioning()) $ver = $this->resolveGitVersion($grp, $nam, $ver, $url);
        $resourceFile = $this->local_file_name($grp, $nam, $ver, $typ, $url, $alt);
        self::debug("source::get_git: resourceFile=$resourceFile");
        if (!file_exists($resourceFile)) {
            if ($url == null) $url = "https://github.com/$grp/$nam/releases/download/$ver/$nam.$typ";
            self::debug("source::get_git: url=$url");
            $this->fetch_dependency($url, $resourceFile);
            $this->process_download($resourceFile, $typ, $nam);
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

    public function local_file_name($group, $name, $version, $type, $url = '', $alt = '')
    {
        self::debug("local_file_name(), workingDir", $this->workingDir);
        $dir = isset($this->workingDir[$alt]) ? $this->workingDir[$alt] : $this->workingDir[''];
        if ($url != null && $url != "") {
            $result = $dir . DIRECTORY_SEPARATOR . basename($url);
        } else {
            $result = $this->slugify($group) . "-" . $this->slugify($name) . '-' . $this->slugify($version) . "." . $type;
            while (strpos($result, "--") !== false) $result = str_replace("--", "-", $result);

            $result = $dir . DIRECTORY_SEPARATOR . $result;
            if ('/' != DIRECTORY_SEPARATOR)
                $result = str_replace('/', DIRECTORY_SEPARATOR, $result);

            self::debug("local_file_name(): $result");
        }
        return $result;
    }

    public function fetch_dependency($url, $local_file)
    {
        self::debug("php-dependency-manager::fetch_dependency(url=$url, local_file=$local_file)");
        if (function_exists("curl_init")) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if (isset($this->proxy))
              curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
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

    public function process_download($resourceFile, $type, $name) {
        self::log("Processing dependency: $name");
        switch($type)
        {
            case "zip": $this->unzip_file($resourceFile); break;
        }
    }

    public function process_dependency($resourceFile, $type, $name) {
        self::log("Processing dependency: $name");
        switch($type)
        {
            case "phar": $this->scan_phar_file($resourceFile, $name); break;
        }
    }

    public function scan_phar_file($phar_file, $name)
    {
        self::debug("Scanning PHAR File: $phar_file");
        $phar = new Phar($phar_file, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $name);
        self::trace("Requiring PHAR: $phar_file");
        @require_once($phar_file);
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

    public function unzip_file($filename)
    {
    $filename=realpath($filename);
    $dir = dirname($filename) . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);

    $zip = new ZipArchive;
    $res = $zip->open($filename);
    if ($res !== FALSE) 
        {
        if (!is_dir($dir)) mkdir($dir);
        if (!is_dir($dir)) throw new Exception("Cannot create output directory: $dir");
        $zip->extractTo($dir);
        $zip->close();
        } else {
            throw new Exception("Unable to unzip: $filename");
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
            // print "\n------------\n"; debug_print_backtrace(0, 5);
            dependency_manager::info("dependency_manager - INITIALIZING CLONE");
            $depmgr = new dependency_manager($vsources, $vworkspace);
        } else if ($vsources != null || $vworkspace != null) {
            // print "\n------------\n"; debug_print_backtrace(0, 5);
            dependency_manager::info("dependency_manager - INITIALIZING ADDING");
            $depmgr->init($vsources, $vworkspace);
        }

        return $depmgr;
    }
}

spl_autoload_register(function ($name) {
    dependency_manager(null, null, $name);
});
