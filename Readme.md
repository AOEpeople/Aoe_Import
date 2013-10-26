# AOE Import

## Description

Generic importer framework for all kinds of things

Author: Fabrizio Branca

## Installation

This git repository comes with a git submodule (the Threadi library). Please remember using the `--recursive` parameter while cloning:

    git clone --recursive https://github.com/AOEmedia/Aoe_Import.git Aoe_Import

The module comes with a modman configuration file.

## Usage

Aoe_Import is configured via config.xml. Instead of modifying Aoe_Import's config.xml please create a new Magento module
that comes with your custom processors and the corresponding config.xml configuration.

## ImportKey

An "importKey" is used to group multiple processors. When importing xml files you have to define what "importKey" you want to use
for this import. This way you can manage multiple xml formats and control what processor should run on which file.
I suggest introducing a new importKey for every file your planning to import in this project (e.g. "products", "categories", "attributes"...)

## Processors

An "importKey" can hold one or more processor. Every processor is defined by a processorIdentifier string you can choose and configure in config.xml
The processor configuration includes:

* class: Magento class group. Your processor class should extend Aoe_Import_Model_Processor_Xml_Abstract.
* pathFilter: regular expression on the path this processor is "subscribed" to.
* nodeType: This is optional and defaults to XMLReader::ELEMENT. In theory you could also subscribe to single xml attributes (but the pathFilter won't match them so far)
* priority: This is optional and defaults to 50. If you have more than one processor operating on the same node you can control the order of execution using this value. Higher priority will be executed first.
* options: This is optional. Define any options here and access them in the processor (using $this->getOptions())

## How it works

Aoe_Import will traverse the xml file and for every xml node it will find all processors that match the node. The current node will be converted into a SimpleXML object and passed to the processors.
It's up to the processor to do whatever is required to be done in order to store the entity. Aoe_Import is not doing any save operations.

Example xml file:

    <import>
        <product>
            <sku>TEST1</sku>
            <!-- ... -->
        </product>
        <product>
            <sku>TEST2</sku>
            <!-- ... -->
        </product>
    </import>

Example configuration:

    <config>
       <aoe_import>
           <test>
               <testProcessor1>
                   <class>aoe_import/processor_dummy</class>
                   <pathFilter><![CDATA[+^//import/product$+]]></pathFilter><!-- regex on xpath that needs to match the current node -->
               </testProcessor1>
           </test>
       </aoe_import>
    </config>

While traversing this xml file following nodes will be visited:

* //import
* //import/product -> will call testProcessor1 and pass `<product><sku>TEST1</sku><!-- ... --></product>` to it
* //import/product/sku
* ...
* //import/product -> will call testProcessor1 and pass `<product><sku>TEST@</sku><!-- ... --></product>` to it
* //import/product/sku
* ...


## Configuration (in config.xml)

    <config>
        <aoe_import>
            <dummyImportKeyA><!-- import key -->
                <dummyProcessor1><!-- processor identifier -->
                    <class>aoe_import/processor_dummy</class>
                    <pathFilter><![CDATA[+^//a/a+]]></pathFilter><!-- regex on xpath that needs to match the current node -->
                    <nodeType>XMLReader::ELEMENT</nodeType><!-- Optional. Defaults to XMLReader::ELEMENT -->
                    <priority>50</priority><!-- Optional. Defaults to 50. Higher priority will be executed first. -->
                    <options>
                        <anycustomoptions>customvalue</anycustomoptions>
                    </options>
                </dummyProcessor1>
                <dummyProcessor2><!-- processor identifier -->
                    <class>aoe_import/processor_dummy</class>
                    <pathFilter><![CDATA[+^//a/b+]]></pathFilter><!-- regex on xpath that needs to match the current node -->
                    <nodeType>XMLReader::ELEMENT</nodeType><!-- Optional. Defaults to XMLReader::ELEMENT -->
                    <priority>50</priority><!-- Optional. Defaults to 50. Higher priority will be executed first. -->
                    <options>
                        <anycustomoptions>customvalue</anycustomoptions>
                    </options>
                </dummyProcessor2>
            </dummyImportKeyA>
            <dummyImportKeyB><!-- import key -->
                <dummyProcessor3><!-- processor identifier -->
                    <class>aoe_import/processor_dummy</class>
                    <pathFilter><![CDATA[+^//a/c+]]></pathFilter><!-- regex on xpath that needs to match the current node -->
                    <nodeType>XMLReader::ELEMENT</nodeType><!-- Optional. Defaults to XMLReader::ELEMENT -->
                    <priority>50</priority><!-- Optional. Defaults to 50. Higher priority will be executed first. -->
                    <options>
                        <anycustomoptions>customvalue</anycustomoptions>
                    </options>
                </dummyProcessor3>
                <dummyProcessor4><!-- processor identifier -->
                    <class>aoe_import/processor_dummy</class>
                    <pathFilter><![CDATA[+^//a/c+]]></pathFilter><!-- regex on xpath that needs to match the current node -->
                    <nodeType>XMLReader::ELEMENT</nodeType><!-- Optional. Defaults to XMLReader::ELEMENT -->
                    <priority>60</priority><!-- Optional. Defaults to 50. Higher priority will be executed first. -->
                    <options>
                        <anycustomoptions>customvalue</anycustomoptions>
                    </options>
                </dummyProcessor4>
            </dummyImportKeyB>
        </aoe_import>
    </config>

## Shell commands

    $ php aoe_import.php
    Available actions:
        -action importXml -importKey <importKey> -files <files> -profilerPath <fileName> -threadPoolSize <int> -threadBatchSize <int>
        -action showConfiguration

### Parameters:

* action: 'importXml' or 'showConfiguration'
* importKey: define the importKey used for this import
* files: one or more file glob patterns separated by PATH_SEPARATER (e.g. 'products_2013-09-*.xml:products_2013-10-*.xml')
* profilerPath: path to a log file with some basic profiling information
* threadPoolSize: number of parallel threads
* threadBatchSize: number of nodes (not processors!) proceesed in one fork

Running the example product importer:

    php aoe_import.php -action importXml -importKey example_products -files <insertPath>/doc/example/products.xml

## Cron Scheduler

Instead of executing import manually from command-line it is possible to wrap them in an cron scheduler task. Use the Aoe_Scheduler module to control and track the tasks in the Magento backend.

## Multi-Threading

Aoe_Import uses the Threadi library to allow running multiple threads in parallel. This comes in handy for two reasons:

1. Beating the memory limit
2. Speed due to parallel executing

Check out http://www.slideshare.net/aoemedia/san-francisco-magento-meetup for more details...