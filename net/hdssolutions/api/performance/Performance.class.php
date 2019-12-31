<?php
    namespace net\hdssolutions\api\performance;

    final class Performance {
        /**
         * [$times description]
         * @var array
         */
        private static $times = [];

        /**
         * [$cache description]
         * @var array
         */
        private static $cache = [];

        /**
         * [start description]
         * @param  [type] $process_name [description]
         * @return [type]               [description]
         */
        public static function start($process_name) {
            // save current time
            self::$cache[$process_name] = microtime(true);
        }

        /**
         * [end description]
         * @param  [type] $process_name [description]
         * @return [type]               [description]
         */
        public static function end($process_name) {
            // check for running
            if (!isset(self::$cache[$process_name])) return;
            // create acumulator
            if (!isset(self::$times[$process_name])) self::$times[$process_name] = 0;
            // add total time
            self::$times[$process_name] += round((microtime(true) - self::$cache[$process_name]) * 1000);
            // remove task
            unset(self::$cache[$process_name]);
        }

        /**
         * [getTimes description]
         * @return [type] [description]
         */
        public static function getTimes() {
            //
            return self::$times;
        }
    }