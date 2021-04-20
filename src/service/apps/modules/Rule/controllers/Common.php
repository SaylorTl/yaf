<?php

trait Common
{

    /**
     * @param $var
     * @param $type
     * @return array|bool|float|int|mixed
     */
    public static function filter($var, $type)
    {
        if ($type & self::T_RAW) {
            return $var;
        }

        // date
        if ($type & self::T_DATE) {
            if (strtotime($var)) {
                return $var;
            }

            return false;
        }

        // int
        if ($type & self::T_INT) {
            if (!is_numeric($var)) {
                return false;
            }

            return intval($var);
        }

        // float
        if ($type & self::T_FLOAT) {
            if (!is_numeric($var)) {
                return false;
            }

            return doubleval($var);
        }

        // boolean
        if ($type & self::T_BOOL) {
            return empty($var) ? 0 : 1;
        }

        // email
        if ($type & self::T_EMAIL) {
            return filter_var($var, FILTER_VALIDATE_EMAIL);
        }

        // url
        if ($type & self::T_URL) {
            if (!filter_var($var, FILTER_VALIDATE_URL)) {
                return filter_var(urldecode($var), FILTER_VALIDATE_URL);
            }

            return filter_var($var, FILTER_VALIDATE_URL);
        }

        // strip html tags
        if ($type & self::T_JSON) {
            if (is_array($var)) {
                return $var;
            }

            $jd = json_decode($var, true);
            if (!is_array($jd)) {
                return false;
            }

            return $jd;
        }

        return trim($var);
    }


    /**
     * @param $definition
     * @param  $request
     * @return array
     * 参数过滤
     */
    protected function parameters($definition, $request)
    {
        $parameters = [];

        foreach ($definition as $key => $filter) {

            $result = $request[$key] ?? null;
            $type = $filter[0];
            $required = $filter[1] ?? false;

            if ($result !== null && $result !== '' && !empty($result)) {
                $result = self::filter($result, $type);
                if ($result === false) {
                    // $this->error(['code' => '10001', 'message' => "Parameter '{$key}' is invalid"]);
                    log_message("Parameter '{$key}' is invalid");
                    $this->error(['code' => 20001]);
                }

            } else {
                if ($required) {
                    // $this->error(['code' => '10002', 'message' => "Parameter '{$key}' is required"]);
                    log_message("Parameter '{$key}' is required");
                    $this->error(['code' => 20002]);
                }
                continue;
            }
            $parameters[$key] = $result;
            // $result && $parameters[$key] = $result;
        }

        return $parameters;
    }


}