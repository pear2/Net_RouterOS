<?php
namespace PEAR2\Net\RouterOS;

require_once 'UtilStateAlteringFeaturesTest.php';

class UtilStateAlteringPersistentFeaturesTest
    extends UtilStateAlteringFeaturesTest
{
    /**
     * @var bool Whether connections should be persistent ones.
     */
    protected $isPersistent = true;
}