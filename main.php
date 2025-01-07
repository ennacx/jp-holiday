<?php
require 'vendor/autoload.php';

try{
    (new JpHoliday\JpHoliday())->generate();
} catch(\Exception $e){
    echo $e->getMessage();
}