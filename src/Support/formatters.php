<?php

if (! function_exists('upperCaseWordOrNull')) {
    function upperCaseWordOrNull($value = null)
    {
        return filled($value) ? ucwords(mb_strtolower(mb_trim($value))) : null;
    }
}
