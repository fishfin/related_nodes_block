<?php

namespace Drupal\related_nodes_block\Plugin\Block;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\related_nodes_block\Module;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Related Nodes' block.
 *
 * @Block(
 *   id = "related_nodes_block",
 *   admin_label = @Translation("Related Nodes Block"),
 *   category = @Translation("Nodes")
 * )
 */

class RelatedNodesBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplay;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $rendererService;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * The current module name.
   *
   * @var string
   */
  protected $moduleName;

  /**
   * The current module name with dashes instead of underscores.
   *
   * @var string
   */
  protected $moduleNameDashed;
  /**
   * The current module name, dynamically fetched using module_handler service.
   *
   * @var string
   */
  protected $moduleLabel;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory to help retrieve system config.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service to get information on current modules.
   * @param \Drupal\Core\Render\RendererInterface $renderer_service
   *   The renderer service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition
      , ConfigFactoryInterface $config_factory
      , EntityDisplayRepositoryInterface $entity_display
      , EntityTypeManagerInterface $entity_type_manager
      , LoggerChannelFactory $logger_factory
      , ModuleHandlerInterface $module_handler
      , RendererInterface $renderer_service
      , RouteMatchInterface $route_match
      , Token $token_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->moduleName = $plugin_definition['provider'];

    $this->configFactory = $config_factory;
    $this->entityDisplay = $entity_display;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerChannel = $logger_factory->get(isset($configuration['label']) ? $configuration['label'] : $module_handler->getName($this->moduleName));
    $this->moduleHandler = $module_handler;
    $this->rendererService = $renderer_service;
    $this->routeMatch = $route_match;
    $this->tokenService = $token_service;

    $this->moduleNameDashed = str_replace('_', '-', $this->moduleName);
    $this->moduleLabel = $this->moduleHandler->getName($this->moduleName);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('current_route_match'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $eyecatch_color = '#ee0000';

    $token_tree = [
      '#token_types' => [$this->moduleName, 'node'],
      '#theme' => 'token_tree_link',
      '#global_types' => FALSE,
    ];
    $rendered_token_tree = $this->rendererService->render($token_tree);

    $attributes_notes = '<dt>'
      . $this->t('Adding an attribute:') . '</dt><dd>'
      . $this->t('Add an attribute on a new line. You can add same attribute multiple times on separte lines. Examples:')
      . '<br><em>class|a-class<br>style|color: blue; background-color: yellowgreen;<br>class|class2<br>style|margin: 0;</em><br>'
      . $this->t('…generates…<br><em>@ph1</em>', ['@ph1' => 'class="a-class" style="color: blue; background-color: yellowgreen; margin: 0;"'])
      . '</dt><dt>'
      . $this->t('Note that not all attributes are rendered by Drupal renderer.')
      . '</dt>';

    $settings = empty($form_state->getTriggeringElement())
        ? $this->getConfiguration()              // same as $this->configuration
        : $form_state->getCompleteFormState()->getValues()['settings'];

    $content_type_curr_node_setting = isset($settings['row_filter']['content_type_curr_node']) ? $settings['row_filter']['content_type_curr_node'] : 'include';
    $content_types_setting = isset($settings['row_filter']['content_types']) ? $settings['row_filter']['content_types'] : [];
    $content_types_negate_setting = isset($settings['row_filter']['content_types_negate']) ? $settings['row_filter']['content_types_negate'] : 1;

    $form['important_note'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Important Note'),
      '#description' => $this->t('By design, this block will render only on node pages. For similar functionality on other pages, creating views is the best way.'),
      '#attributes' => [
        'style' => "color: {$eyecatch_color};"
      ]
    ];

    $form['block_display'] = [
      '#type' => 'details',
      '#title' => $this->t('Block Display'),
      '#open' => TRUE,
    ];

    $form['block_display']['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix Text'),
      '#default_value' => isset($settings['block_display']['prefix']) ? $settings['block_display']['prefix'] : '',
      '#maxlength' => 255,
      '#size' => 60,
    ];

    $form['block_display']['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix Text'),
      '#default_value' => isset($settings['block_display']['suffix']) ? $settings['block_display']['suffix'] : '',
      '#maxlength' => 255,
      '#size' => 60,
    ];

    $form['block_display']['attr'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Container Attributes'),
      '#default_value' => isset($settings['block_display']['attr']) ? $settings['block_display']['attr'] : '',
      '#description' => $this->t('<dt>Attributes that will be attached to the container of this block. @ph2 <em>@ph3</em> is always added.</dt><dt>This field supports tokens. @ph1</dt>', ['@ph1' => $rendered_token_tree, '@ph2' => 'Class', '@ph3' => "{$this->moduleNameDashed}--container"])
        . $attributes_notes,
      '#placeholder' => "class|my-cont-class--[{$this->moduleName}:display-type-dashed]",
      '#resizable' => FALSE,
      '#rows' => 4,
    ];

    $form['block_display']['addl_css_classes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add Additional Container Class(es)'),
      '#default_value' => isset($settings['block_display']['addl_css_classes']) ? $settings['block_display']['addl_css_classes'] : 1,
      '#description' => $this->t('Adds additional class(es) to the container of this block, e.g. <em>@ph1</em> and <em>@ph2</em>.', ['@ph1' => "{$this->moduleNameDashed}--container--least-viewed-today", '@ph2' => "{$this->moduleNameDashed}--container--view-mode"]),
    ];

    $form['row_filter'] = [
      '#type' => 'details',
      '#title' => $this->t('Row Filter'),
      '#open' => TRUE,
    ];

    $form['row_filter']['specific'] = [
      '#type' => 'checkbox',
      '#title' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'specific']]),
      '#ajax' => [
        'callback' => [$this, 'acbRefreshViewModes'],
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'aw-display-mode-options-view-modes',
      ],
      '#default_value' => isset($settings['row_filter']['specific']) ? $settings['row_filter']['specific'] : 0,
    ];

    $form['row_filter']['node_title_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Node'),
      '#ajax' => [
        'callback' => [$this, 'acbRefreshViewModes'],
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'aw-display-mode-options-view-modes',
      ],
      '#autocomplete_route_name' => 'related_nodes_block.autocomplete_node',
      '#description' => $this->t('Node number or node title are allowed.<br>After selecting a node using the autocomplete, you can move your cursor away from the field and wait a few seconds for the View Mode to update.'),
      '#default_value' => isset($settings['row_filter']['node_title_id']) ? $settings['row_filter']['node_title_id'] : '',
      '#xplaceholder' => $this->t('Start typing here...'),
      '#states' => [
        'required' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $content_types = node_type_get_names();
    // Sort associative array alphabetically by name (default is by machine names)
    asort($content_types);
    $form['row_filter']['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Types'),
      '#ajax' => [
        'callback' => [$this, 'acbRefreshViewModes'],
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'aw-display-mode-options-view-modes',
      ],
      '#default_value' => $content_types_setting,
      '#description' => $this->t('None checked is equivalent to all checked.'),
      '#options' => $content_types,
      '#states' => [
        'required' => [
          [ ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE]] ,
          'and',
          [ [':input[name="settings[row_filter][content_type_curr_node]"]' => ['value' =>  'ignore']],
            'or',
            [':input[name="settings[row_filter][content_type_curr_node]"]' => ['value' =>  'exclude']],
          ],
        ],
        'visible' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['row_filter']['content_types_negate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Negate the condition'),
      '#ajax' => [
        'callback' => [$this, 'acbRefreshViewModes'],
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'aw-display-mode-options-view-modes',
      ],
      '#default_value' => $content_types_negate_setting, // isset($settings['row_filter']['content_types_negate']) ? $settings['row_filter']['content_types_negate'] : FALSE,
      '#states' => [
        'visible' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['row_filter']['content_type_curr_node'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Type of Current Node this Block is on'),
      '#ajax' => [
        'callback' => [$this, 'acbRefreshViewModes'],
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => 'aw-display-mode-options-view-modes',
      ],
      '#default_value' => $content_type_curr_node_setting,
      '#description' => $this->t('When "Ignore", at least 1 effective %ph1 > %ph2 must be selected; when "Exclude", at least 2.', ['%ph1' => $form['row_filter']['#title'], '%ph2' => $form['row_filter']['content_types']['#title']]),
      '#options' => [
        'ignore' => $this->t('Ignore'),
        'include' => $this->t('Include'),
        'exclude' => $this->t('Exclude'),
      ],
      '#states' => [
        'required' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
        'visible' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['row_filter']['ref_ts'] = [
      '#type' => 'select',
      '#title' => $this->t('Reference Timestamp'),
      '#default_value' => isset($settings['row_filter']['ref_ts']) ? $settings['row_filter']['ref_ts'] : 'node_created',
      '#options' => [
        'node_created' => $this->t('Node Created'),
        'node_changed' => $this->t('Node Changed'),
      ],
      '#required' => TRUE,
      '#states' => [
        'invisible' => [
          [':input[name="settings[row_filter][specific]"]' => ['checked' => TRUE],],
          'or',
          [':input[name="settings[row_display][type]"]' => [
              ['value' => 'most_viewed_today'],
              ['value' => 'least_viewed_today'],
              ['value' => 'most_viewed'],
              ['value' => 'least_viewed'],
              ['value' => 'random'],
            ],
          ],
        ],
      ],
    ];

    $form['row_display'] = [
      '#type' => 'details',
      '#title' => $this->t('Row Display'),
      '#open' => TRUE,
    ];

    $form['row_display']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Type'),
      '#default_value' => isset($settings['row_display']['type']) ? $settings['row_display']['type'] : '',
      '#empty_option' => $this->t('Select'),
      '#options' => [
        'prev' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'prev']]),
        'next' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'next']]),
        'most_viewed_today' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'most_viewed_today']]),
        'least_viewed_today' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'least_viewed_today']]),
        'most_viewed' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'most_viewed']]),
        'least_viewed' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'least_viewed']]),
        'first' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'first']]),
        'last' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'last']]),
        'random' => $this->tokenService->replace("[{$this->moduleName}:display-type]", [$this->moduleName => ['display-type' => 'random']]),
        ],
      '#states' => [
        'required' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
        'visible' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $internal_page_cache = $this->moduleHandler->moduleExists('page_cache') ? $this->moduleHandler->getName('page_cache') : '';
    $form['row_display']['caching_note'] = [
      '#type' => 'item',
      '#description' => $internal_page_cache
            ? $this->t('Currently <em>@ph1</em> core module is enabled and cache maximum age is set to <em>@ph2 seconds</em>.'
                      . '<dt>You can do one of the following:</dt>'
                      . '<dd>&bullet; Make no changes. This means that anonymous users will see the random effect only every @ph2 seconds.</dd>'
                      . '<dd>&bullet; Change cache maximum age in <a href=":ph3" target="_blank">System Performance Settings</a>. This is an expert setting, please be careful on production sites.</dd>'
                      . '<dd>&bullet; If you understand <a href=":ph4" target="_blank">Drupal caching</a>, you can disable <a href=":ph5" target="_blank">@ph1</a>.  This is an expert setting, please be careful on production sites.</dd>'
                      . '<dd>&bullet; If you are uncomfortable with any of these, choose a @ph6 other than @ph7.</dd></dt>',
                  ['@ph1' => $internal_page_cache,
                   '@ph2' => $this->configFactory->get('system.performance')->get('cache.page.max_age'),
                   ':ph3' => Url::fromRoute('system.performance_settings')->toString(),
                   ':ph4' => 'https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts#internal',
                   ':ph5' => 'https://www.drupal.org/docs/administering-a-drupal-site/internal-page-cache',
                   '@ph6' => $form['row_display']['type']['#title'],
                   '@ph7' => $form['row_display']['type']['#options']['random']])
            : '', // the Internal Page Check module
      '#states' => [
        'visible' => [
          ':input[name="settings[row_display][type]"]' => ['value' => 'random'],
        ]
      ],
    ];

    $tmp = implode(', ',
      array_map(function($item) { return '"' . $item . ' n"'; }, array_values($form['row_display']['type']['#options'])));
    $form['row_display']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#default_value' => isset($settings['row_display']['limit']) ? $settings['row_display']['limit'] : 1,
      '#description' => $this->t('Limits the number of nodes retrieved. Read it as %ph1.', ['%ph1' => $tmp]),
      '#min' => 1,
      '#required' => TRUE,
      '#size' => 8,
      '#states' => [
        'required' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
        'visible' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['row_display']['skip'] = [
      '#type' => 'number',
      '#title' => $this->t('Skip'),
      '#default_value' => isset($settings['row_display']['skip']) ? $settings['row_display']['skip'] : 0,
      '#description' => $this->t('Skips first n of the filtered nodes.'),
      '#min' => 0,
      '#required' => TRUE,
      '#size' => 8,
      '#states' => [
        'required' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
        'visible' => [
          ':input[name="settings[row_filter][specific]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['row_display']['reverse_order'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse natural order'),
      '#default_value' => isset($settings['row_display']['reverse_order']) ? $settings['row_display']['reverse_order'] : 0,
      '#states' => [
        'invisible' => [
          [':input[name="settings[row_filter][specific]"]' => ['checked' => TRUE],],
          'or',
          [':input[name="settings[row_display][type]"]' => ['value' => 'random'],],
          'or',
          [':input[name="settings[row_display][limit]"]' => ['value' => 1],],

        ],
      ],
    ];

    $form['row_display']['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display Mode'),
      '#default_value' => isset($settings['row_display']['mode']) ? $settings['row_display']['mode'] : 'linked_text',
      '#empty_option' => $this->t('Select'),
//    '#error_message' => $this->t('Invalid'),
      '#options' => [
        'linked_text' => $this->t('Linked Text'),
        'view_mode' => $this->t('View Mode'),
      ],
      '#required' => TRUE,
    ];

    $form['row_display']['mode_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('@ph1 Options', ['@ph1' => $form['row_display']['mode']['#title']]),
      '#states' => [
        'visible' => [
          ':input[name="settings[row_display][mode]"]' => [
              ['value' => 'linked_text'],
              ['value' => 'view_mode']
          ],
        ],
      ],
    ];

    $form['row_display']['mode_options']['linked_text_note'] = [
      '#type' => 'item',
      '#title' => $this->t('%ph1 is used as a fallback when at least one filtered node has missing selected %ph2.', ['%ph1' => $form['row_display']['mode']['#options']['linked_text'], '%ph2' => $form['row_display']['mode']['#options']['view_mode']]),
      '#states' => [
        'visible' => [
          ':input[name="settings[row_display][mode]"]' => ['value' => 'view_mode'],
        ],
      ],
    ];

    $form['row_display']['mode_options']['linked_text'] = [
      '#type' => 'textfield',
      '#title' => $form['row_display']['mode']['#options']['linked_text'],
      '#default_value' => isset($settings['row_display']['mode_options']['linked_text']) ? $settings['row_display']['mode_options']['linked_text'] : '[node:title]',
      '#description' => $this->t('<dt>This field supports tokens. @ph1 Use tokens that result only in numeric or string output to avoid unexpected behaviour.</dt><dt>Literals are translated at runtime.</dt>', ['@ph1' => $rendered_token_tree]),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#size' => 60,
      '#token_types' => [$this->moduleName, 'node'],
      '#element_validate' => ['token_element_validate'],
    ];

    $form['row_display']['mode_options']['linked_text_maxlen'] = [
      '#type' => 'number',
      '#title' => $form['row_display']['mode']['#options']['linked_text'] . $this->t(' Maximum Length'),
      '#default_value' => isset($settings['row_display']['mode_options']['linked_text_maxlen']) ? $settings['row_display']['mode_options']['linked_text_maxlen'] : 40,
      '#description' => $this->t('<dt>Includes 1 character for the ellipsis "<em>…</em>". Word-safe truncation will occur.</dt><dt>Enter <em>0</em> for no truncation.</dt>'),
      '#min' => 0,
      '#required' => TRUE,
      '#size' => 8,
    ];

    $view_modes = $this->getViewModes($settings);
    $form['row_display']['mode_options']['view_mode'] = [
      '#type' => 'select',
      '#title' => $form['row_display']['mode']['#options']['view_mode'],
      '#default_value' => isset($settings['row_display']['mode_options']['view_mode']) ? $settings['row_display']['mode_options']['view_mode'] : 'teaser',
      '#description' => $this->t('Some view modes like "Revision comparison" or Email related are automatically not considered.'),
      '#empty_option' => $this->t('Select'),
      '#options' => $view_modes,
      '#sort_options' => TRUE,
      '#sort_start' => $view_modes['teaser'] ? 2 : 1,
      '#states' => [
        'invisible' => [
          [':input[name="settings[row_display][mode]"]' => ['value' => 'linked_text'], ]
        ],
        'required' => [
          [':input[name="settings[row_display][mode]"]' => ['value' => 'view_mode'], ]
        ],
      ],
      '#validated' => FALSE,
      '#prefix' => '<div id="aw-display-mode-options-view-modes">',
      '#suffix' => '</div>',
    ];
    if (empty($view_modes)) {
      $form['row_display']['mode_options']['view_mode']['#empty_option'] = $this->t('No selection available');
    }

    $form['row_display']['mode_options']['hr_1'] = [
      '#type' => 'item',
      '#description' => '<hr>',
    ];

    $form['row_display']['mode_options']['prefix_suffix_div'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prefix/Suffix Text in separate <em>div</em>'),
      '#default_value' => isset($settings['row_display']['mode_options']['prefix_suffix_div']) ? $settings['row_display']['mode_options']['prefix_suffix_div'] : 0,
      '#states' => [
        'visible' => [
          ':input[name="settings[row_display][mode]"]' => ['value' => 'linked_text'],
        ],
      ],
    ];

    $form['row_display']['mode_options']['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix Text'),
      '#default_value' => isset($settings['row_display']['mode_options']['prefix']) ? $settings['row_display']['mode_options']['prefix'] : '',
      '#maxlength' => 255,
      '#size' => 60,
      '#token_types' => [$this->moduleName, 'node'],
      '#element_validate' => ['token_element_validate'],
    ];

    $form['row_display']['mode_options']['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix Text'),
      '#default_value' => isset($settings['row_display']['mode_options']['suffix']) ? $settings['row_display']['mode_options']['suffix'] : '',
      '#maxlength' => 255,
      '#size' => 60,
      '#token_types' => [$this->moduleName, 'node'],
      '#element_validate' => ['token_element_validate'],
    ];

    $form['row_display']['mode_options']['prefix_suffix_note'] = [
      '#type' => 'item',
      '#description' => $this->t('<dt>These fields support tokens. @ph1 Use tokens that result only in numeric or string output to avoid unexpected behaviour.</dt>'
          . '<dt>Like <em>@ph2</em>, these are also linked to the target node.</dt>'
          . '<dt>Literals are translated at runtime.</dt>', ['@ph1' => $rendered_token_tree, '@ph2' => $form['row_display']['mode']['#options']['linked_text']]),
    ];

    $form['row_display']['attr'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Row Attributes'),
      '#default_value' => isset($settings['row_display']['attr']) ? $settings['row_display']['attr'] : '',
      '#description' => $this->t('<dt>Attributes that will be attached to each node of output. @ph2 <em>@ph3</em> is always added.</dt><dt>This field supports tokens. @ph1</dt>', ['@ph1' => $rendered_token_tree, '@ph2' => 'Class', '@ph3' => "{$this->moduleNameDashed}--row"])
        . $attributes_notes,
      '#placeholder' => "class|my-row-class--[{$this->moduleName}:display-type-dashed]--[{$this->moduleName}:counter]",
      '#resizable' => FALSE,
      '#rows' => 4,
    ];

    $form['row_display']['addl_css_classes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add Additional Row Class(es)'),
      '#default_value' => isset($settings['row_display']['addl_css_classes']) ? $settings['row_display']['addl_css_classes'] : 1,
      '#description' => $this->t('Adds additional class(es) to each node of output, e.g. <em>@ph1</em> and <em>@ph2</em>.', ['@ph1' => "{$this->moduleNameDashed}--row--most-viewed", '@ph2' => "{$this->moduleNameDashed}--row--most-viewed--2"]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  protected function baseConfigurationDefaults() {
    // Unchecking "Display Title" by default
    return array_merge(parent::baseConfigurationDefaults(), ['label_display' => '']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $settings = $form_state->getValues();

    if ($settings['row_filter']['specific']) {
      $error = TRUE;
      $specific_node_id = $this->extractNodeId($settings['row_filter']['node_title_id']);
      if ($specific_node_id) {
        $specific_node = $this->entityTypeManager->getStorage('node')->load($specific_node_id);
        if ($specific_node instanceof NodeInterface) {
          $error = FALSE;
        }
      }
      if ($error) {
        $form_state->setError($form['row_filter']['node_title_id'], $this->t('The specific node could not be validated.'));
      }
    }

    switch ($settings['row_filter']['content_type_curr_node']) {
      case 'ignore':
        if (count($this->getTrueSelection($settings['row_filter']['content_types'], $settings['row_filter']['content_types_negate'])) < 1) {
          $t_args = [
            '%ph1' => $form['row_filter']['#title'],
            '%ph2' => $form['row_filter']['content_types']['#title'],
            '%ph3' => $form['row_filter']['content_type_curr_node']['#title'],
            '%ph4' => $form['row_filter']['content_type_curr_node']['#options'][$settings['row_filter']['content_type_curr_node']],
          ];
          $form_state->setError($form['row_filter']['content_types'], $this->t('At least 1 effective %ph1 > %ph2 field is required with %ph1 > %ph3 value "%ph4".', $t_args));
        }
        break;
      case 'exclude':
        if (count($this->getTrueSelection($settings['row_filter']['content_types'], $settings['row_filter']['content_types_negate'])) < 2) {
          $t_args = [
            '%ph1' => $form['row_filter']['#title'],
            '%ph2' => $form['row_filter']['content_types']['#title'],
            '%ph3' => $form['row_filter']['content_type_curr_node']['#title'],
            '%ph4' => $form['row_filter']['content_type_curr_node']['#options'][$settings['row_filter']['content_type_curr_node']],
          ];
          $form_state->setError($form['row_filter']['content_types'], $this->t('At least 2 effective %ph1 > %ph2 field required with %ph1 > %ph3 value "%ph4".', $t_args));
        }
        break;
      default:
        break;
    }

    // After AJAX update of field view_modes, it loses its conditional #states.required
    // property (see https://www.drupal.org/project/drupal/issues/1091852).
    // Hence specifically validating.
    if ($settings['row_display']['mode'] == 'view_mode'
        and empty($settings['row_display']['mode_options']['view_mode'])) {
      $t_args = [
        '%ph1' => $form['row_display']['#title'],
        '%ph2' => $form['row_display']['mode_options']['#title'],
        '%ph3' => $form['row_display']['mode_options']['view_mode']['#title'],
      ];
      $form_state->setError($form['row_display']['mode_options']['view_mode'], $this->t('Please select an item in the list for %ph1 > %ph2 > %ph3.', $t_args));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $renderable_block = [];

    $curr_node = $this->routeMatch->getParameter('node');

    // This block displays only on nodes.
    if (! ($curr_node instanceof NodeInterface)) {
      return $renderable_block;
    }

    $config = $this->getConfiguration();
    $display_type = $config['row_filter']['specific'] ? 'specific' : $config['row_display']['type'];
    $row_prefix = trim($config['row_display']['mode_options']['prefix']) == '' ? '' : $this->t($config['row_display']['mode_options']['prefix']);
    $row_suffix = trim($config['row_display']['mode_options']['suffix']) == '' ? '' : $this->t($config['row_display']['mode_options']['suffix']);

    $token_replacement = [
      'counter' => 0,
      'display-type' => $display_type,
      'display-type-dashed' => $display_type,
    ];

    $rel_nodes = $this->getRelatedNodes($curr_node);

    $rows_renderable = []; $rows_store = [];

    $display_mode = $config['row_display']['mode'];

    if ($display_mode == 'view_mode') {
      $view_builder = $this->entityTypeManager->getViewBuilder('node');
      $content_types_view_modes_save_map = [];

      foreach ($rel_nodes as $rel_node_id => $rel_node) {
        $rel_node_type = $rel_node->getType();
        if (!array_key_exists($rel_node_type, $content_types_view_modes_save_map)) {
          $content_types_view_modes_save_map[$rel_node_type] = $this->entityDisplay->getViewModeOptionsByBundle('node', $rel_node_type);
        }
        // View mode not found for at a node, then blank out rows, change
        // display mode to linked_text so that everything will be rendered
        if (array_key_exists($config['row_display']['mode_options']['view_mode'], $content_types_view_modes_save_map[$rel_node_type])) {
          $renderable = $view_builder->view($rel_node, $config['row_display']['mode_options']['view_mode']);
          $rows_renderable[$rel_node_id] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ["{$this->moduleNameDashed}--row--content"],
            ],
            $display_mode => $renderable,
          ];
        } else {
          $rows_renderable = [];
          $display_mode = 'linked_text';
          break;
        }
      }
    }

    foreach ($rel_nodes as $rel_node_id => $rel_node) {
      $detokenized_text = $row_prefix;
      if ($detokenized_text != '') {
        $detokenized_text = $this->tokenService->replace($detokenized_text, [$this->moduleName => $token_replacement, 'node' => $rel_node]);
      }
      $rows_store[$rel_node_id]['prefix'] = $detokenized_text;

      $detokenized_text = $row_suffix;
      if ($detokenized_text != '') {
        $detokenized_text = $this->tokenService->replace($detokenized_text, [$this->moduleName => $token_replacement, 'node' => $rel_node]);
      }
      $rows_store[$rel_node_id]['suffix'] = $detokenized_text;

      // not sure if getting a link makes a db call, but avoiding multiple calls
      // anyway
      if (!($display_mode == 'view_mode' and $row_prefix == '' and $row_suffix == '')) {
        $rows_store[$rel_node_id]['url'] = Url::fromRoute('entity.node.canonical', ['node' => $rel_node_id], []);
      }
    }

    if ($display_mode == 'linked_text') {
      $token_replacement['counter'] = 0;
      foreach ($rel_nodes as $rel_node_id => $rel_node) {
        $token_replacement['counter']++;
        $linked_text = $config['row_display']['mode_options']['linked_text'];
        $linked_text = $this->tokenService->replace($linked_text, [$this->moduleName => $token_replacement, 'node' => $rel_node]);
        if ($config['row_display']['mode_options']['linked_text_maxlen']) {
          $linked_text = Unicode::truncate($linked_text, $config['row_display']['mode_options']['linked_text_maxlen'], TRUE, TRUE);
        }
        if (!$config['row_display']['mode_options']['prefix_suffix_div']) {
          $linked_text = $rows_store[$rel_node_id]['prefix'] . $linked_text . $rows_store[$rel_node_id]['suffix'];
        }
        $rows_renderable[$rel_node_id] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ["{$this->moduleNameDashed}--row--content"],
          ],
          $display_mode => Link::fromTextAndUrl($linked_text, $rows_store[$rel_node_id]['url'])->toRenderable(),
        ];
      }
    }

    $row_attr_default = "class|{$this->moduleNameDashed}--row\n"
      . ($config['row_display']['addl_css_classes']
          ? ("class|{$this->moduleNameDashed}--row--[{$this->moduleName}:display-type-dashed]\n"
        . "class|{$this->moduleNameDashed}--row--[{$this->moduleName}:display-type-dashed]--[{$this->moduleName}:counter]\n")
      : '');
    $row_attr_text = $row_attr_default . $config['row_display']['attr'];

    $wrapped_rows = [];
    $token_replacement['counter'] = 0;
    $prefix_suffix_div = ($config['row_display']['mode_options']['prefix_suffix_div'] or ($display_mode == 'view_mode'));

    foreach ($rel_nodes as $rel_node_id => $rel_node) {
      $token_replacement['counter']++;

      $row_attr_text = $this->tokenService->replace($row_attr_text, [$this->moduleName => $token_replacement, 'node' => $rel_node]);
      $row_attr = Module\text_to_renderable_attr($row_attr_text);

      $wrapped_rows[$rel_node_id] = [
        '#type' => 'container',
        '#attributes' => $row_attr,
      ];

      if ($prefix_suffix_div and $rows_store[$rel_node_id]['prefix'] != '') {
        $wrapped_rows[$rel_node_id]['prefix'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ["{$this->moduleNameDashed}--row--prefix"],
          ],
          'link' => Link::fromTextAndUrl($rows_store[$rel_node_id]['prefix'], $rows_store[$rel_node_id]['url'])->toRenderable(),
        ];
      }

      $wrapped_rows[$rel_node_id]['content'] = $rows_renderable[$rel_node_id];

      if ($prefix_suffix_div and $rows_store[$rel_node_id]['suffix'] != '') {
        $wrapped_rows[$rel_node_id]['suffix'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ["{$this->moduleNameDashed}--row--suffix"],
          ],
          'link' => Link::fromTextAndUrl($rows_store[$rel_node_id]['suffix'], $rows_store[$rel_node_id]['url'])->toRenderable(),
        ];
      }
    }

    if (!empty($wrapped_rows)) {
      $display_mode_dashed = str_replace('_', '-', $display_mode);
      $block_div_attr_default = "class|{$this->moduleNameDashed}--container\n"
        . ($config['block_display']['addl_css_classes']
          ? ("class|{$this->moduleNameDashed}--container--[{$this->moduleName}:display-type-dashed]\n"
            . "class|{$this->moduleNameDashed}--container--{$display_mode_dashed}\n")
          : '');
      $detokenized_text = $block_div_attr_default . $config['block_display']['attr'];
      $detokenized_text = $this->tokenService->replace($detokenized_text, [$this->moduleName => $token_replacement]);
      $renderable_block = [
        '#type' => 'container',
        '#attributes' => Module\text_to_renderable_attr($detokenized_text),
      ];

      if (trim($config['block_display']['prefix']) != '') {
        $renderable_block['prefix'] = [
          '#plain_text' => $this->t($config['block_display']['prefix']),
          '#prefix' => "<h2 class=\"h2 {$this->moduleNameDashed}--container--prefix\">",
          '#suffix' => '</h2>',
        ];
      }

      $renderable_block['rows'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ["{$this->moduleNameDashed}--rows"],
        ],
        'rows' => $wrapped_rows,
      ];

      if (trim($config['block_display']['suffix']) != '') {
        $renderable_block['suffix'] = [
          '#plain_text' => $this->t($config['block_display']['suffix']),
          '#prefix' => "<h2 class=\"h2 {$this->moduleNameDashed}--container--suffix\">",
          '#suffix' => '</h2>',
        ];
      }
    }

    return empty($renderable_block) ? [] : [$renderable_block];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Code copied from NextPre module, not 100% sure what is happening here.
    // My guess is that it checks here if the current page is a node,
    // if it is, then it adds node:* to all the cache-checks, meaning
    // if any nodes change, then this block cache will also be
    // invalidated. Yeah, that makes sense. :)
    $node = $this->routeMatch->getParameter('node');
    if (!empty($node) and $node instanceof NodeInterface) {
      return Cache::mergeTags(parent::getCacheTags(), ['node:*']);
    } else {
      return parent::getCacheTags();
    }
  }

  /**
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The updated form element.
   */
  public function acbRefreshViewModes($form, FormStateInterface $form_state) {
    return $form['settings']['row_display']['mode_options']['view_mode'];
  }

  /**
   * Extracts Node Id from an autocompleted string of the form:
   * 'Some Node Title (Node_Id)'
   *
   * @param string $node_title_id
   *   A string containing title and node-id.
   *
   * @return string
   *   Node Id or empty string if could not extract.
   */
  private function extractNodeId($node_title_id) {
    $matches = [];
    preg_match("/.+\s\(([^\)]+)\)/", $node_title_id, $matches);
    return (isset($matches[1]) and is_numeric($matches[1])) ? $matches[1] : 0;
  }

  /**
   * Generates a list of all related nodes based on the block config.
   *
   * @param \Drupal\node\NodeInterface $ref_node
   *   The reference node relative to which related nodes will be retrieved.
   *
   * @return array
   *   An associative array of node_id => \Drupal\node\Entity\Node
   */
  private function getRelatedNodes(NodeInterface $ref_node) {
    $rel_node_ids = [];
    $config = $this->getConfiguration();
    $ref_node_id = $ref_node->id();

    $curr_node = [
      'id' => $ref_node_id,
      'content_type' => $ref_node->getType(),
      'created' => $ref_node->getCreatedTime(),
      'changed' => $ref_node->getChangedTime(),
    ];

    if ($config['row_filter']['specific']) {
      $specific_node_id = $this->extractNodeId($config['row_filter']['node_title_id']);
      if (is_numeric($specific_node_id)) {
        // Do not show this related node if ref_node is the same
        if ($specific_node_id != $ref_node_id) {
          $rel_node_ids[] = (int) $specific_node_id;
        }
      } else {
        $this->loggerChannel->error($this->t('Invalid specific node id detected (%ph1).', ['%ph1' => $specific_node_id]));
      }
    } else {
      $query = \Drupal::service('database')
          ->select('node_field_data', 'nfd')
          ->fields('nfd', ['nid'])
          ->condition('nfd.status', 1 , '=')
          ->condition('nfd.nid', (int) $curr_node['id'], '<>')
          ->range(is_numeric($config['row_display']['skip']) ? $config['row_display']['skip'] : 0, is_numeric($config['row_display']['limit']) ? $config['row_display']['limit'] : 1);

      $content_types_query_group = NULL;
      switch ($config['row_filter']['content_type_curr_node']) {
        case 'include':
          $content_types_query_group = $query->orConditionGroup()
              ->condition('nfd.type', $curr_node['content_type'], '=');
          break;
        case 'exclude':
          $content_types_query_group = $query->andConditionGroup()
              ->condition('nfd.type', $curr_node['content_type'], '<>');
          break;
        default:
          $content_types_query_group = $query->andConditionGroup();
          break;
      }

      $filter_content_types = $this->getTrueSelection($config['row_filter']['content_types'], 0);
      if (count($filter_content_types)) {
        $content_types_query_group->condition('nfd.type'
            , array_keys($filter_content_types)
            , $config['row_filter']['content_types_negate'] ? 'NOT IN' : 'IN');
      }
      $query->condition($content_types_query_group);

      $ref_ts_col = ($config['row_filter']['ref_ts'] == 'node_created') ? 'created' : 'changed';

      switch ($config['row_display']['type']) {
        case 'prev':
          $query->condition("nfd.{$ref_ts_col}", $curr_node[$ref_ts_col], '<')
              ->orderBy("nfd.{$ref_ts_col}", 'DESC');
          break;
        case 'next':
          $query->condition("nfd.{$ref_ts_col}", $curr_node[$ref_ts_col], '>')
              ->orderBy("nfd.{$ref_ts_col}", 'ASC');
          break;
        case 'most_viewed_today':
          $query->addJoin('LEFT OUTER', 'node_counter', 'nc', 'nfd.nid = nc.nid');
          $query->orderBy('nc.daycount', 'DESC')
            ->orderBy("nfd.{$ref_ts_col}", 'DESC');
          break;
        case 'least_viewed_today':
          $query->addJoin('LEFT OUTER', 'node_counter', 'nc', 'nfd.nid = nc.nid');
          $query->orderBy('nc.daycount', 'ASC')
            ->orderBy("nfd.{$ref_ts_col}", 'DESC');
          break;
        case 'most_viewed':
          $query->addJoin('LEFT OUTER', 'node_counter', 'nc', 'nfd.nid = nc.nid');
          $query->orderBy('nc.totalcount', 'DESC')
            ->orderBy("nfd.{$ref_ts_col}", 'DESC');
          break;
        case 'least_viewed':
          $query->addJoin('LEFT OUTER', 'node_counter', 'nc', 'nfd.nid = nc.nid');
          $query->orderBy('nc.totalcount', 'ASC')
            ->orderBy("nfd.{$ref_ts_col}", 'DESC');
          break;
        case 'first':
          $query->orderBy("nfd.{$ref_ts_col}", 'ASC');
          break;
        case 'last':
          $query->orderBy("nfd.{$ref_ts_col}", 'DESC');
          break;
        case 'random':
          $query->orderRandom();
          break;
        default:
          break;
      }

      try {
        $rel_node_ids = array_keys($query->execute()->fetchAllAssoc('nid'));
      } catch (\Exception $e) {
        $this->loggerChannel->error($e->getMessage());
      }

      if ($config['row_display']['reverse_order']) {
        $rel_node_ids = array_reverse($rel_node_ids);
      }
    }

    return Node::loadMultiple($rel_node_ids);
  }

  /**
   * Where no selection from a list (typically checkboxes) means all are
   * selected, this method converts selection to true values.
   *
   * @param array $assoc_list
   *   An associative array of the values of any selection list in the form.
   * @param integer $negate
   *   The value of negate selection.
   *
   * @return array
   *   An associative array of the true values of the selection in the form.
   */
  private function getTrueSelection($assoc_list, $negate) {
    $no_selection = array_reduce($assoc_list
      , function($not_selected, $item) { return ($not_selected and !$item); }
      , TRUE);

    $true_selection = [];
    foreach ($assoc_list as $key => $value) {
      if (($no_selection or $value) xor $negate) {
        $true_selection[$key] = $key;
      }
    }

    return $true_selection;
  }

  /**
   * Returns an alphabetically sorted superset of all available view modes for
   * - individual node OR
   * - specifically selected content_types OR
   * - all content_types
   *
   * @param array $settings
   *   A string or an array (list) containing the content type(s).
   *
   * @return array
   *   An associative array of view modes with machine_name => label.
   */

  private function getViewModes($settings) {
    $view_modes = [];

    if (isset($settings['row_filter']['specific']) and $settings['row_filter']['specific']) {
      if (isset($settings['row_filter']['node_title_id']) and $settings['row_filter']['node_title_id']) {
        //$view_modes = $this->getNodeViewModes($settings['row_filter']['node_title_id']);
        $matches = [];
        preg_match("/.+\s\(([^\)]+)\)/", $settings['row_filter']['node_title_id'], $matches);
        $nid = $matches[1];

        // Get bundle
        $storage = $this->entityTypeManager->getStorage('node');
        $node = $storage->load($nid);
        if (!empty($node)) {
          $content_type = $node->getType();
          $view_modes = $this->entityDisplay->getViewModeOptionsByBundle('node', $content_type);
          ;
        }
      }
    } else {
      $content_type_keys = ($settings['row_filter']['content_type_curr_node'] == 'include')
          ? array_keys($settings['row_filter']['content_types'])
          : $this->getTrueSelection(isset($settings['row_filter']['content_types']) ? $settings['row_filter']['content_types'] : node_type_get_names()
              , $settings['row_filter']['content_types_negate']);

      foreach ($content_type_keys as $content_type) {
        $view_modes = array_merge($view_modes, $this->entityDisplay->getViewModeOptionsByBundle('node', $content_type));
      }
    }

    // Cleansing View Modes:
    // - alphabetically sorting
    // - moving 'teaser' to the top
    // - removing 'diff' and 'email_*'
    asort($view_modes);
    $view_mode_teaser = [];
    foreach ($view_modes as $machine_name => $view_mode_name) {
      if ($machine_name == 'teaser') {
        $view_mode_teaser = [$machine_name => $view_mode_name];
        unset($view_modes[$machine_name]);
        continue;
      }

      if (in_array($machine_name, ['diff'])
        or substr($machine_name, 0, 5) == 'email') {
        unset($view_modes[$machine_name]);
      }
    }

    return $view_mode_teaser + $view_modes;
  }

}
