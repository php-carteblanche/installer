CarteBlanche Installers
========================

This package defines some custom [Composer](http://getcomposer.org/) installers for the
[CarteBlanche](https://github.com/php-carteblanche/carteblanche) framework to install
Tools and Bundles.

It is required for each CarteBlanche installation (*and is included in the CarteBlanche
`composer.json` itself*).

## Install tools

To define a custom CarteBlanche's `Tool`, just use the following `composer.json` definitions:

    "name": "carte-blanche/tool-ToolName",
    "type": "carte-blanche-tool",
    "require": {
        ...
        "atelierspierrot/carte-blanche-installers": "dev-master"
    },
    "repositories": [{
        "type": "vcs", "url": "https://github.com/php-carteblanche/installer"
    }]

A tool MUST be named as `carte-blanche/tool-NAME` and be typed as `carte-blanche-tool`.


## Install bundles

To define a custom CarteBlanche's `Bundle`, just use the following `composer.json` definitions:

    "name": "carte-blanche/bundle-BundleName",
    "type": "carte-blanche-bundle",
    "require": {
        ...
        "atelierspierrot/carte-blanche-installers": "dev-master"
    },
    "autoload": { 
        "psr-0": { "BundleName": "path/to/library" },
        "classmap": [ "path/to/library/Controller" ]
    },
    "repositories": [{
        "type": "vcs", "url": "https://github.com/php-carteblanche/installer"
    }]

A bundle MUST be named as `carte-blanche/bundle-NAME` and be typed as `carte-blanche-bundle`.
