<?php

class dependency_manager_versioning
{
    public static $testResponse = null;

    public static function gitVersionList($owner, $repo)
    {

        $releaseUrl = "https://api.github.com/repos/$owner/$repo/releases";

        if (self::$testResponse)
            return self::$testResponse;

        if (function_exists("curl_init")) {
            $ch = curl_init();
            // print("\nurl=$url");
            curl_setopt($ch, CURLOPT_URL, $releaseUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, "bhoogter");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
        } else return null;


        return $result;
    }

    public static function gitVersions($owner, $repo)
    {
        $versionList = self::gitVersionList($owner, $repo);
        $versionsListObj = new xml_file();
        $versionsListObj->loadJson($versionList);
        $versions = $versionsListObj->lst("//tag_name");
        usort($versions, 'version_compare');

        return $versions;
    }

    public static function resolveGitVersion($owner, $repo, $match, &$url)
    {
        $url = "";

        $versionList = self::gitVersionList($owner, $repo);
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
