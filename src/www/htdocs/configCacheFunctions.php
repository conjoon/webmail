<?php
/**
 * conjoon
 * (c) 2002-2009 siteartwork.de/conjoon.org
 * licensing@conjoon.org
 *
 * $Author$
 * $Id$
 * $Date$
 * $Revision$
 * $LastChangedDate$
 * $LastChangedBy$
 * $URL$
 */

/**
 * Provides functionality for caching the configuration file and reading out
 * cached versions of the configuration file.
 *
 * @author Thorsten Suckow-Homberg <ts@siteartwork.de>
 */

    /**
     * Tries to cache the config and load cached instances of the configuration.
     *
     * @return mixed Array or Conjoon_Config_Array
     *
     * @see conjoon_parseConfig
     */
    function conjoon_initConfigCache()
    {
        // check here if the include_path has already been set. If this is the case,
        // we can assume that we can cache the configuration file.
        $conjoonSet = @include_once('Conjoon/Config/Array.php');

        if (!$conjoonSet) {
            return conjoon_parseConfig();
        }

        $handle = null;

        // include path should be set using the webserver. Load the config!

        $didFileExist = file_exists('./_configCache/config.ini.php');

        if ($didFileExist) {
            $lastCacheModified  = filemtime('./_configCache/config.ini.php');
            $lastConfigModified = filemtime('./config.ini.php');

            if ($lastCacheModified === false || $lastConfigModified === false) {
                return conjoon_parseConfig();
            }
        }

        if (!$didFileExist || ($lastConfigModified > $lastCacheModified)) {

            $initialConfig = conjoon_parseConfig();

            $config = new Conjoon_Config_Array($initialConfig);

            $serialized = serialize($config);

            file_put_contents('./_configCache/config.ini.php',
                "<?php die(\"Forbidden!\"); ?>___DRTL___" .
                $serialized
            );

            return $config;

        } else {

            $serialized = file_get_contents('./_configCache/config.ini.php');

            $lines = explode("___DRTL___", $serialized);

            $config = unserialize($lines[1]);

            // check if the library_path is set, and adjust the include_path if necessary
            if (($incPath = $config->environment->include_path) != null) {
                set_include_path(get_include_path() . PATH_SEPARATOR . $incPath);
            }

            return $config;
        }

    }

    /**
     * Parses the configuration file and sets the include path if found in the
     * configuration file.
     *
     */
    function conjoon_parseConfig()
    {
        // config failed to init, so we assume we have to load the config and parse it
        $initialConfig = parse_ini_file('./config.ini.php', true);

        // take care of default cache
        if (isset($initialConfig['cache'])) {
            if (!$initialConfig['cache']['default.caching']) {
                unset($initialConfig['cache']);
            } else {
                $defaults = array();

                // extract defaults
                foreach ($initialConfig['cache'] as $key => $value) {
                    if (strpos($key, 'default.') === 0) {
                        $defaults[substr($key, 8)] = $initialConfig['cache'][$key];
                        unset($initialConfig['cache'][$key]);
                    }
                }

                // get the cache namespaces
                $cacheBlocks =& $initialConfig['cache'];
                $namespaces  = array();
                $unsets      = array();
                foreach ($cacheBlocks as $key => $value) {
                    $ns = explode(".", $key, 3);
                    if (array_key_exists($ns[0].'.'.$ns[1], $unsets)) {
                        continue;
                    }
                    if ($ns[2] == 'caching' && !$value) {
                        $unsets[$ns[0].'.'.$ns[1]] = true;
                    } else {
                        $namespaces[$ns[0].'.'.$ns[1]] = true;
                    }
                }

                // first off, unset all cache blocks that have caching set to 0
                foreach ($unsets as $key => $value) {
                    foreach ($cacheBlocks as $ckey => $cvalue) {
                         if (strpos($ckey, $key) === 0) {
                            unset($cacheBlocks[$ckey]);
                         }
                    }
                }

                foreach ($namespaces as $key => $value) {
                    foreach ($defaults as $defaultKey => $defaultValue) {
                        $m = $key . '.' . $defaultKey;
                        if (!array_key_exists($m, $cacheBlocks)) {
                            $cacheBlocks[$m] = $defaultValue;
                        }
                    }
                }
            }
        }

        // check if the library_path is set, and adjust the include_path if necessary
        if (($incPath = $initialConfig['environment']['include_path']) != null) {
           set_include_path(get_include_path() . PATH_SEPARATOR . $incPath);
        }

        return $initialConfig;
    }

?>