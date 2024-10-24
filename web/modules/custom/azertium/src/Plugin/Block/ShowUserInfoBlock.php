<?php

declare(strict_types=1);

namespace Drupal\azertium\Plugin\Block;

use Drupal;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a show user info block.
 *
 * @Block(
 *   id = "azertium_show_user_info",
 *   admin_label = @Translation("Show User Info"),
 *   category = @Translation("Custom"),
 * )
 */
 class ShowUserInfoBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly Connection $connection,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // function to get uid from uri or drupal currentuser
    $uid = $this->getUserIdFromUri();
    // function to get total comments of current user
    $totalComments = $this->getTotalComments($uid);
    // function to  get the last five comments of current user
    $lastFiveComments = $this->getLastFiveComments($uid);
    // function to get total comment words of current user
    $totalWords = $this->getTotalWords($uid);
    return [
      '#theme' => 'azertium_block',
      '#total' => $totalComments,
      '#last_five_comments' => $lastFiveComments,
      '#total_words' => $totalWords,
    ];
  }

  /**
  * {@inheritdoc}
  */

  public function getTotalWords($uid){
      $totalWords = 0;
      $query = $this->connection->select('comment_field_data', 'cfd');
      $query->join('comment__comment_body', 'ccb', 'cfd.cid = ccb.entity_id');
      $query->fields('ccb',['comment_body_value'])
      ->condition('cfd.uid', $uid)
      ->execute();

      $result = $query->execute()->fetchAll();
      $totalWords = 0;
      foreach ($result as $record) {
        $totalWords += str_word_count($record->comment_body_value);
      }
      return $totalWords;
     }
  /**
  * {@inheritdoc}
  */

  public function getLastFiveComments($uid){
    $lastComments = 0;
    $query = $this->connection->select('comment_field_data', 'cfd');
    $query->join('node_field_data', 'nfd', 'cfd.entity_id = nfd.nid');
    $query->fields('cfd',['subject'])
    ->fields('nfd',['title'])
    ->condition('cfd.uid', $uid)
    ->orderBy('cfd.created', 'DESC')
    ->range(0,5);

    $result = $query->execute()->fetchAll();
    $lastComments = [];
    foreach ($result as $record) {
      $lastComments[] = ['comment' => $record->subject, 'title' => $record->title];
    }
    return $lastComments;
  }   
  /**
  * {@inheritdoc}
  */
  public function getTotalComments($uid){
    $number = 0;
    $number = $this->connection->select('comment_field_data', 'cfd')
    ->condition('uid', $uid)
    ->countQuery()
    ->execute()
    ->fetchField();

    return $number;

  }

  /**
  * {@inheritdoc}
  */
  public function getUserIdFromUri(){
    $uidFromUri = \Drupal::routeMatch()->getRawParameter('user');
    if(is_null($uidFromUri)){
      // If null return current user id from site context
      return \Drupal::currentUser()->id();
    }
      //if not, return uid from uri context
    return $uidFromUri;
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
  protected function blockAccess(AccountInterface $account): AccessResult {
    // Hide block if user not logged
    $logged = \Drupal::currentUser()->isAuthenticated();
    if($logged){
      return AccessResult::allowedIf(TRUE);
    }
    else {
      return AccessResult::forbidden();
    }
  }

}
