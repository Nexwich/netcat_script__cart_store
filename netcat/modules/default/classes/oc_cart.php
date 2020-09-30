<?php

class OcCart extends nc_record {

  public $catalog_id = 1; // id сайта
  public $domain = ''; // домен для куки
  public $time = 60 * 60 * 24 * 365; // 365 дней

  protected $table_name = 'Message388';

  protected $users = array();

  protected $mapping = array(
    "id" => "Message_ID",
    "_generate" => true,

    "Checked" => "Checked",
    "Subdivision_ID" => "Subdivision_ID",
    "Sub_Class_ID" => "Sub_Class_ID",
    "User_ID" => "User_ID",
    "Created" => "Created",
    "IP" => "IP",
    "LastUpdated" => "LastUpdated",
    "LastUser_ID" => "LastUser_ID",
    "LastIP" => "LastIP",

    "Hash" => "Hash",
    "Composition" => "Composition",
  );

  protected $serialized_properties = [
    'Composition',
  ];


  protected $nc_core;
  protected $hash;
  protected $is_recovery = false;
  protected $cart_name = '';


  public function __construct () {
    parent::__construct();

    $this->nc_core = nc_Core::get_object();
    $this->nc_core->event->bind($this, array('netshopOrderCreated' => 'netshopOrderCreated'));
    $this->nc_core->event->bind($this, array('netshopCartChanged' => 'netshopCartChanged'));
    $this->nc_core->event->bind($this, array('netshopCartClear' => 'netshopCartClear'));

    $this->time = time() + $this->time;
    $this->cart_name = 'nc_netshop_'.$this->catalog_id.'_cart';


    if (!$_COOKIE['cart_hash']) {
      $this->hash = md5('Bnwec3' . $this->time);
      setcookie('cart_hash', $this->hash, $this->time, "/", $this->domain);
    }else $this->hash = $_COOKIE['cart_hash'];

    $this->load_by_hash($this->hash);

    if (!$this->is_cart()) $this->recovery_cart();
  }

  /*
   * Восстановить корзину из базы данных
   */
  public function recovery_cart () {
    $netshop = nc_netshop::get_instance();

    if (!empty($this['Composition'])) {
      $netshop->cart->clear();
      $this->is_recovery = true;

      foreach ($this['Composition'] as $item) {
        $netshop->cart->add_item($item['Class_ID'], $item['Message_ID'], $item['Qty'], true, $item['OrderParameters']);
      }
    }
  }

  protected function create () {
    $netshop = nc_netshop::get_instance();

    if (!$this->get_id()) {
      $this->set_values([
        "Checked" => 1,
        "Subdivision_ID" => 353,
        "Sub_Class_ID" => 799,
        "User_ID" => 81,
        "Created" => date('Y-m-d H:i:s'),
        "IP" => $_SERVER['REMOTE_ADDR'],
      ]);
      $this->set('Hash', $this->hash);
      $this->set('Composition', $netshop->cart->get_items());
      $this->save();
    }

    return $this;
  }


  public function load_by_hash ($hash) {
    $hash = $this->nc_core->db->escape($hash);
    $sql = "SELECT `Message_ID` FROM `" . $this->table_name . "` WHERE `Hash` = '" . $hash . "'";
    if ($hash) $id = $this->nc_core->db->get_var($sql);

    if (!empty($id)) $this->load($id);

    return $this;
  }


  /*
   * Записать корзину в куки
   */
  public function cart_save () {
    $netshop = nc_netshop::get_instance();

    if ($this->is_cart()) {
      if (!$this->get_id()) $this->load_by_hash($this->hash);

      if ($this->get_id()) {
        if ($netshop->cart->get_items() != $this['Composition']) {
          $this->set_values([
            "LastUpdated" => date('Y-m-d H:i:s'),
            "LastUser_ID" => 81,
            "LastIP" => $_SERVER['REMOTE_ADDR'],
          ]);
          $this->set('Composition', $netshop->cart->get_items());
          $this->save();
        }
      }else $this->create();
    }else $this->clear();
  }

  /*
   * Очистить куки
   */
  public function clear () {
    if ($this->get_id()) {
      $this->delete();
      setcookie('cart_hash', null, $this->time, "/", $this->domain);
    }
  }

  /*
   * Проверить наличие продуктов к корзине
   * @return bool
   */
  public function is_cart () {
    $netshop = nc_netshop::get_instance();
    return $netshop->cart->get_item_count(true);
  }

  /*
   * Событие При изменение корзины
   */
  public function netshopCartChanged () {
    if ($this->is_recovery === false) {
      if ($this->is_cart()) {
        $this->cart_save();
      }else {
        $this->clear();
      }
    }
  }


  /*
   * Событие После оформления заказа
   */
  public function netshopOrderCreated () {
    $this->clear();
  }

  /*
  * Событие очистить корзину
  */
  public function netshopCartClear () {
    $this->clear();
  }
}
