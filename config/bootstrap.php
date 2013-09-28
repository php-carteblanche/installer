<?php

// bootstrap.php generated by CarteBlanche

namespace App\Bootstrap;
use App\Bootstrap\SafeBootstrap;
class ContainerBootstrap extends SafeBootstrap {

    protected $bundles = array(
    );
    
    protected $prod_stacks = array(
        'database'          =>'initDatabase'
    );
    
    protected $dev_stacks = array(
        'database'          =>'initDatabase',
        'unit_test'         =>'initUnitTest'
    );
    
}
