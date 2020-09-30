## Старт

Создайте новый компонент с полями  
`Hash` - строка (Хеш),  
`Composition` - текстовое поле (Состав).

Разместите скрипт в папке или скопируйте папку в корень.
`netcat/modules/default/classes/oc_cart.php`

В скрипте укажите id сайта, домен к которому будут присвоены куки и время их хранения. Так же укажите таблицу компонента который вы только что создали.
```
  public $catalog_id = 1;
  public $domain = '';
  public $time = 60 * 60 * 24 * 365; // 365 дней
  
  protected $table_name = 'Message388';
```

В файле `function.inc.php` подключите скрипт например

```php
<?
$nc_core = nc_Core::get_object();
$nc_core->event->add_listener(nc_event::AFTER_MODULES_LOADED, function () {

  require __DIR__ . '/classes/oc_cart.php';
  $oc_cart = new OcCart();

});
?>
```

## Дополнительно

В системе имеется ошибка с событием на удаление товара из корзины и отстутствует событие на очистке корзины. Для решения проблемы о которой извесно в компании вам потребуется самостоятлно внести изменения в 2 файла.

`netcat/modules/netshop/nc_netshop.class.php`  
Добавить константу событие об очищении корзины

```php
const EVENT_CART_CLEAR = 'netshopCartClear';
```

`netcat/modules/netshop/classes/cart.php`  
Добавить  новый метод который будет сообщать об очищении корзины
```
protected function on_cart_clear() {
  nc_core::get_object()->event->execute(nc_netshop::EVENT_CART_CLEAR);
}
```
Изменить метод очистки корзины добавив в конец `$this->on_cart_clear();`
```
    public function clear() {
        [...]
        $this->on_cart_clear();
    }
```

Исправить ошибку при удалении товара. Найдите строку `$this->on_qty_change($removed_item, $removed_qty);` в методе `remove_item` и разместите ее после удаления товара `$this->items->remove_item_by_id($component_id, $item_id);`. Сейчас она стоит до и из за этого вы будете получать старое значение. Выглядеть должно так:
```php
    public function remove_item($component_id, $item_id) {
        $component_id = (int)$component_id;
        $item_id = (int)$item_id;

        $items = $this->get_items();
        $removed_item = $items->get_item_by_id($component_id, $item_id);
        if ($removed_item) {
            $removed_qty = -$removed_item['Qty']; // отрицательное количество
            $removed_item['Qty'] = 0;

            unset($this->raw_contents[$component_id][$item_id]);

            // (sic, $items — это клон $this->items)
            $this->items->remove_item_by_id($component_id, $item_id);

            $this->on_qty_change($removed_item, $removed_qty);
        }
        return true;
    }
```