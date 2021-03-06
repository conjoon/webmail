<?php
/**
 * conjoon
 * (c) 2007-2015 conjoon.org
 * licensing@conjoon.org
 *
 * conjoon
 * Copyright (C) 2014 Thorsten Suckow-Homberg/conjoon.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
 * $Author$
 * $Id$
 * $Date$
 * $Revision$
 * $LastChangedDate$
 * $LastChangedBy$
 * $URL$
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 * @copyright 2012 The conjoon Open Source Project
 * @package Conjoon\Filter\Date
 *
 */

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

/**
 * @see Zend_Date
 */
require_once 'Zend/Date.php';

/**
 * Converts a UTC data to a local date.
 *
 * Note: This class will not check whether the date passed to "filter" is
 * actually a UTC date.
 *
 * @category   Filter
 * @package    Conjoon_Filter
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 *
 * @deprecated use Conjoon_Date_Format::utcToLocal
 */
class Conjoon_Filter_DateUtcToLocal implements Zend_Filter_Interface
{
    /**
     *@type string
     */
    const OPTIONS_TIMEZONE = 'timezone';

    /**
     * Stores thetimezone the UTC dates passed to filter() will be converted to.
     *
     * @type string
     */
    protected $_timezone;

    /**
     * Constructs a new instance of Conjoon_Filter_DateUtcToLocal
     *
     * @param Array|Zend_Config|null $options The argument holds
     * configuration options for this instance. If the argument is null, the
     * current timezone as found in date_default_timezone_get() will be used.
     * Otherwise, the key self::OPTIONS_TIMEZONE will be looked up and used
     * for converting the UTC date.
     *
     * @throws Conjoon\Filter\Exception If $options 'timezone' value is not
     * valid, if the option-key for the timezone is missing or if $options
     * is an invalid type
     * @see Conjoon\Filter\DateUtcToLocal::setTimezone()
     */
    public function __construct($options = null)
    {
        if (($options !== null &&
            !($options instanceof Zend_Config) && !is_array($options))
            || (is_array($options) && !array_key_exists(self::OPTIONS_TIMEZONE, $options)
            )
            ) {
            /**
             * @see Conjoon\Filter\Exception
             */
            require_once 'Conjoon/Filter/Exception.php';

            throw new Conjoon_Filter_Exception(
                "Invalid configuration for argument \"options\""
            );
        }

        if ($options === null) {
            $currentTimezone = date_default_timezone_get();

            $options = array(
                self::OPTIONS_TIMEZONE => $currentTimezone
            );
        }

        if ($options instanceof Zend_Config) {
            $options = $options->toArray();
        }

        // check whether timezone is configured
        if (!array_key_exists(self::OPTIONS_TIMEZONE, $options)) {
            /**
             * @see Conjoon\Filter\Exception
             */
            require_once 'Conjoon/Filter/Exception.php';

            throw new Conjoon_Filter_Exception(
                "Missing configuration option for argument \"options\". "
                . "Key \"".self::OPTIONS_TIMEZONE."\" is missing"
            );
        }

        $this->setTimezone($options[self::OPTIONS_TIMEZONE]);
    }

    /**
     * Sets the timezone this filter will use to convert UTC dates to.
     *
     * @param string $timezone The timezone to use for converting UTC dates to.
     *
     * @return bool true if the timezone of this instance was successfully set
     * to the value of the passed argument
     *
     * @throws Conjoon_Filter_Exception if the passed argument is not a
     * valid timezone
     */
    public function setTimezone($timezone)
    {
        $currentTimezone    = date_default_timezone_get();
        $configuredTimezone = $timezone;

        // try to set the timezone here and fail if invalid
        $res = @date_default_timezone_set($configuredTimezone);

        if ($res === false) {
            date_default_timezone_set($currentTimezone);

            /**
             * @see Conjoon\Filter\Exception
             */
            require_once 'Conjoon/Filter/Exception.php';

            throw new Conjoon_Filter_Exception(
                "Timezone \"".$configuredTimezone."\" is invalid."
            );
        }
        date_default_timezone_set($currentTimezone);

        $this->_timezone = $configuredTimezone;

        return true;

    }

    /**
     * Returns the timezone currently used with this instance.
     *
     * @return string
     */
    public function getTimezone()
    {
        return $this->_timezone;
    }

    /**
     * Converts the passed string to a date in the timezone of self::$_timezone.
     *
     * Returns a UTC date converted to the local date as determined by the
     * timezonefound in self::$_timezone, which can be specified when
     * instantiating this class. The date sttring returned will be in the format
     * of YYYY-MM-dd HH:mm:ss.
     * Note: The passed argument must already be a UTC date! This method will
     * not check whether the passed argument's timezone is UTC
     * This method will gracefully fall back to teh default date of
     * 1970-01-01 00:00:00 if it could not convert the passed argument properly.
     *
     * @param  mixed $value
     * @return string
     */
    public function filter($value)
    {
        /**
         * @see Conjoon_Date_Format
         */
        require_once 'Conjoon/Date/Format.php';

        return Conjoon_Date_Format::utcToLocal($value, $this->getTimezone());
    }
}
