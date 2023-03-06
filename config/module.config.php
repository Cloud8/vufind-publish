<?php
namespace Dbib\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'Dbib\Controller\DbibController' => 'VuFind\Controller\CartControllerFactory',
            //'Dbib\Controller\DokliefController' => 'VuFind\Controller\CartControllerFactory',
            //'Dbib\Controller\RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            //'Dbib\Controller\OaiController' => 'VuFind\Controller\AbstractBaseFactory',
            //'Dbib\Controller\ViewController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            //'Record' => 'Dbib\Controller\RecordController',
            //'Doklief'=> 'Dbib\Controller\DokliefController',
            'Dbib'=> 'Dbib\Controller\DbibController',
            'Opus'=> 'Dbib\Controller\DbibController',
            //'View'   => 'Dbib\Controller\ViewController',
            //'OAI'    => 'Dbib\Controller\OaiController',
            //'oai'    => 'Dbib\Controller\OaiController',
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            // 'Dbib\OAI\Server' => 'VuFind\OAI\ServerFactory',
        ],
        'aliases' => [
            // 'Dbib\RecordTabPluginManager' => 'Dbib\RecordTab\PluginManager',
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'ils_driver' => [ 
                'factories' => [
                    // 'Dbib\ILS\Driver\LBS4' => 'Dbib\ILS\Driver\Factory::getLBS4',
                    // 'Dbib\\ILS\\Driver\\Folio' => 'Dbib\\ILS\\Driver\\FolioFactory',
                    // 'Dbib\\ILS\\Driver\\Opus' => 'Dbib\\ILS\\Driver\\OpusFactory',
                 ],
                 'aliases' => [
                               // 'lbs4' => 'Dbib\ILS\Driver\LBS4',
                               // 'folio' => 'Dbib\\ILS\\Driver\\Folio',
                               // 'opus' => 'Dbib\\ILS\\Driver\\Opus',
                              ]
            ],
            'recommend' => [ 
				 'aliases' => [ 'authorid' => 'Dbib\Recommend\AuthorId' ]
            ],
            'recorddriver' => [
                'factories' => [
                    // 'Dbib\RecordDriver\SolrOpus' => 'Dbib\RecordDriver\Factory::getSolrOpus',
                    'Dbib\RecordDriver\SolrDbib' => 'Dbib\RecordDriver\Factory::getSolrDbib',
                    // 'Dbib\RecordDriver\SolrOpac' => 'Dbib\RecordDriver\Factory::getSolrOpac',
                    // 'Dbib\RecordDriver\WorldCat' => 'Dbib\RecordDriver\Factory::getWorldCat',
                ],
                'aliases' => [
                    'solrdbib' => 'Dbib\RecordDriver\SolrDbib',
                    // 'solropus' => 'Dbib\RecordDriver\SolrOpus',
                    // 'solropac' => 'Dbib\RecordDriver\SolrOpac',
                    // 'wordlcat' => 'Dbib\RecordDriver\WorldCat',
                ]
            ],
            'recordtab' => [
                'factories' => [
                   'Dbib\RecordTab\References' => 'Dbib\RecordTab\Factory::getReferences',
                   'Dbib\RecordTab\Citations' => 'Dbib\RecordTab\Factory::getCitations',
                ],
                'aliases' => [
                    'references' => 'Dbib\RecordTab\References',
                    'citations' => 'Dbib\RecordTab\Citations',
                ]
            ],
        ],
    ],
];

$recordRoutes = [
     // 'view' => 'View', 
];

// Define dynamic routes -- controller => [route name => action]
$dynamicRoutes = [
//     'MyResearch' => ['userList' => 'MyList/[:id]', 'editList' => 'EditList/[:id]'],
//     'LibraryCards' => ['editLibraryCard' => 'editCard/[:id]'],
];

$staticRoutes = [
    'Dbib/Home', 'Dbib/Upload', 'Dbib/Admin', 'Dbib/Edit', 'Dbib/Security', 
    'Dbib/View', 'Dbib/Subject', 
    // 'View/Barrier', 'View/Text', 'View/Video', 
    // 'Doklief/Home', 'Doklief/Admin', 'Doklief/View', 'Doklief/Login'
];

// hard coded in module/VuFind/src/VuFind/Route/RouteGenerator.php
$nonTabRecordActions = [
    'AddComment', 'DeleteComment', 'AddTag', 'DeleteTag', 'Save', 'Email', 
    'SMS', 'Cite', 'Export', 'RDF', 'Hold', 'Home', 'StorageRetrievalRequest', 
    'AjaxTab', 'ILLRequest', 'PDF', 'Epub', 'LinkedText', 'Permalink',
    'Restricted', 'Edit', 'View',
];

// Never worked :
// $routeGenerator = new \VuFind\Route\RouteGenerator($nonTabRecordActions);
$routeGenerator = new \VuFind\Route\RouteGenerator();
// GH2022-11 : VF 9
// $routeGenerator->addNonTabRecordActions($config, $nonTabRecordActions);
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

// Add the home route last
$config['router']['routes']['home'] = [
    'type' => 'Laminas\Router\Http\Literal',
    'options' => [
        'route'    => '/',
        'defaults' => [
            'controller' => 'index',
            'action'     => 'Home',
        ]
    ]
];

return $config;
