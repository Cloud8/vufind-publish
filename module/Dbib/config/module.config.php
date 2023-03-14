<?php
namespace Dbib\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'Dbib\Controller\DbibController' => 'VuFind\Controller\CartControllerFactory',
            'Dbib\Controller\RecordController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Dbib\Controller\ViewController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
        ],
        'aliases' => [
            'Record' => 'Dbib\Controller\RecordController',
            'Dbib' => 'Dbib\Controller\DbibController',
            'View' => 'Dbib\Controller\ViewController',
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
            'recommend' => [ 
				 'aliases' => [ 'authorid' => 'Dbib\Recommend\AuthorId' ]
            ],
            'recorddriver' => [
                'factories' => [
                    'Dbib\RecordDriver\SolrDCTerms' => 'Dbib\RecordDriver\Factory::getSolrDCTerms',
                ],
                'aliases' => [
                    'solrdcterms' => 'Dbib\RecordDriver\SolrDCTerms',
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
];

// Define dynamic routes -- controller => [route name => action]
$dynamicRoutes = [
];

$staticRoutes = [
    'Dbib/Home', 'Dbib/Upload', 'Dbib/Admin', 'Dbib/Edit', 'Dbib/Subject', 
    'View/Stream',
];

// hard coded in module/VuFind/src/VuFind/Route/RouteGenerator.php
$nonTabRecordActions = [
    'AddComment', 'DeleteComment', 'AddTag', 'DeleteTag', 'Save', 'Email', 
    'SMS', 'Cite', 'Export', 'RDF', 'Hold', 'Home', 'StorageRetrievalRequest', 
    'AjaxTab', 'ILLRequest', 'PDF', 'Epub', 'LinkedText', 'Permalink',
    'Restricted', 'Edit', 'View',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
// VuFind 9:
// $routeGenerator->addNonTabRecordActions($config, $nonTabRecordActions);
// VuFind before 9:
// modify nonTabRecordActions module/VuFind/src/VuFind/Route/RouteGenerator.php
// $routeGenerator = new \VuFind\Route\RouteGenerator($nonTabRecordActions);
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
