<?php
namespace Dpub\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'Dpub\Controller\DpubController' => 'VuFind\Controller\CartControllerFactory',
            //'Dpub\Controller\DokliefController' => 'VuFind\Controller\CartControllerFactory',
            //'Dpub\Controller\RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            //'Dpub\Controller\OaiController' => 'VuFind\Controller\AbstractBaseFactory',
            //'Dpub\Controller\ViewController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            //'Record' => 'Dpub\Controller\RecordController',
            //'Doklief'=> 'Dpub\Controller\DokliefController',
            'Dpub'=> 'Dpub\Controller\DpubController',
            'Opus'=> 'Dpub\Controller\DpubController',
            //'View'   => 'Dpub\Controller\ViewController',
            //'OAI'    => 'Dpub\Controller\OaiController',
            //'oai'    => 'Dpub\Controller\OaiController',
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            // 'Dpub\OAI\Server' => 'VuFind\OAI\ServerFactory',
        ],
        'aliases' => [
            // 'Dpub\RecordTabPluginManager' => 'Dpub\RecordTab\PluginManager',
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'ils_driver' => [ 
                'factories' => [
                    // 'Dpub\ILS\Driver\LBS4' => 'Dpub\ILS\Driver\Factory::getLBS4',
                    // 'Dpub\\ILS\\Driver\\Folio' => 'Dpub\\ILS\\Driver\\FolioFactory',
                    // 'Dpub\\ILS\\Driver\\Opus' => 'Dpub\\ILS\\Driver\\OpusFactory',
                 ],
                 'aliases' => [
                               // 'lbs4' => 'Dpub\ILS\Driver\LBS4',
                               // 'folio' => 'Dpub\\ILS\\Driver\\Folio',
                               // 'opus' => 'Dpub\\ILS\\Driver\\Opus',
                              ]
            ],
            'recommend' => [ 
				 'aliases' => [ 'authorid' => 'Dpub\Recommend\AuthorId' ]
            ],
            'recorddriver' => [
                'factories' => [
                    // 'Dpub\RecordDriver\SolrOpus' => 'Dpub\RecordDriver\Factory::getSolrOpus',
                    'Dpub\RecordDriver\SolrDpub' => 'Dpub\RecordDriver\Factory::getSolrDpub',
                    // 'Dpub\RecordDriver\SolrOpac' => 'Dpub\RecordDriver\Factory::getSolrOpac',
                    // 'Dpub\RecordDriver\WorldCat' => 'Dpub\RecordDriver\Factory::getWorldCat',
                ],
                'aliases' => [
                    'solrdpub' => 'Dpub\RecordDriver\SolrDpub',
                    // 'solropus' => 'Dpub\RecordDriver\SolrOpus',
                    // 'solropac' => 'Dpub\RecordDriver\SolrOpac',
                    // 'wordlcat' => 'Dpub\RecordDriver\WorldCat',
                ]
            ],
            'recordtab' => [
                'factories' => [
                   'Dpub\RecordTab\References' => 'Dpub\RecordTab\Factory::getReferences',
                   'Dpub\RecordTab\Citations' => 'Dpub\RecordTab\Factory::getCitations',
                ],
                'aliases' => [
                    'references' => 'Dpub\RecordTab\References',
                    'citations' => 'Dpub\RecordTab\Citations',
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
    'Dpub/Home', 'Dpub/Upload', 'Dpub/Admin', 'Dpub/Edit', 'Dpub/Security', 
    'Dpub/View', 'Dpub/Subject', 
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
