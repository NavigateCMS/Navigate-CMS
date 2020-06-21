<?php
require_once(NAVIGATE_PATH.'/lib/webgets/product.php');
require_once(NAVIGATE_PATH.'/lib/webgets/properties.php');
require_once(NAVIGATE_PATH.'/lib/webgets/webuser.php');
require_once(NAVIGATE_PATH.'/lib/webgets/content.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/coupons/coupon.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/orders/order.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/shipping_methods/shipping_method.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/payment_methods/payment_method.class.php');

/*
    steps to complete an order
    1) cart
    2) sign in / registration / purchase without registering (not developed yet)
    3) shipping address / billing address
    4) choose shipping method
    5) summary and confirmation
    6) payment
        (on error) payment failed -> choose alternative payment method and repeat step 6
    7) order complete
*/


function nvweb_cart($vars=array())
{
    global $DB;
    global $website;
    global $current;
    global $session;
    global $webuser;

    // TODO +: allow getting info & update cart via AJAX calls
    // TODO ++: add support for product variants and options
    // TODO +++: add support for discounted price of product by volume
    // TODO ++++: allow multiple coupons and discounts
    // TODO: support multi currency carts?
    $out = '';
    $is_ajax = false;

    if(!isset($session['cart']))
    {
        // TODO: allow using other weight and size units (and currency?) instead of the defaults assigned by the website
        $cart = array(
            'checkout_step'     => 'cart',
            'customer'          => 0,
            'currency'          => $website->currency,
            'currency_symbol'   => product::currencies($website->currency, true),
            'weight_unit'       => $website->weight_unit,
            'size_unit'         => $website->size_unit,
            'lines'             => array(), // [ timestamp_added, id, quantity, price, subtotal ]
            'last_updated'      => 0,
            'products'          => 0, // number of different products
            'quantity'          => 0, // sum of total product units
            'subtotal'          => 0,
            'subtotal_before_coupon' => 0,
            'weight'            => 0,
            'coupon'            => null,
            'coupon_amount'     => null,
            'coupon_code'       => "",
            'coupon_error'      => "",
            'coupon_data'      => "",
            'shipping_price'    => 0,
            'discount_value'    => 0,
            'address_shipping'  => null,
            'address_billing'   => null,
            'shipping_method'   => null,
            'shipping_rate'     => null,
            'shipping_method_data' => "",
            'customer_notes'    => "",
            'total'             => 0,
            'order_id'          => 0    // order ID, after has been inserted in database
        );
    }
    else
    {
        $cart = $session['cart'];
    }

    switch($vars['mode'])
    {
        case 'process':
            // process action by URL parameters (only affects relative to the "cart" step)
            switch($_REQUEST['action'])
            {
                case 'add_product':
                    $product = new product();
                    $product->load($_REQUEST['product']);
                    $quantity = value_or_default($_REQUEST['quantity'], 1);
                    // TODO: include product "option" (variant), when implemented

                    if(!empty($product->id))
                    {
                        // is the product already in the cart?
                        $found = false;
                        for($i = 0; $i < count($cart['lines']); $i++)
                        {
                            if($cart['lines'][$i]['id'] == $product->id)
                            {
                                $found = true;
                                $cart['lines'][$i]['quantity'] = $cart['lines'][$i]['quantity'] + floatval($quantity);
                                $cart['lines'][$i]['subtotal'] = $cart['lines'][$i]['price'] * $cart['lines'][$i]['quantity'];
                            }
                        }

                        if(!$found)
                        {
                            // TODO: price could also be based on quantity ranges
                            // by now use fixed price per unit
                            // warning: (in the future) quantity may be a decimal number, price should be rounded
                            $price = $product->get_price();

                            $cart['lines'][] = array(
                                'timestamp_added' => core_time(),
                                'id' => $product->id,
                                'sku' => $product->sku,
                                'name' => $product->dictionary[$current['lang']]['title'],
                                'option' => "", // future use
                                'quantity' => $quantity,
                                'currency' => $product->base_price_currency,
                                'price' => $price['current'],
                                'original_price' => $price['old'], // including tax
                                'base_price' => $price['base_price'],
                                'tax_value' => $price['tax_value'],
                                'base_price_tax_amount' => $price['tax_amount'],
                                'subtotal_without_taxes' => $price['base_price'] * $quantity,
                                'subtotal_tax_amount' => $price['tax_amount'] * $quantity,
                                'subtotal' => $price['current'] * $quantity,
                                'coupon_amount' => 0,
                                'subtotal_with_taxes_without_coupon' => $price['current'] * $quantity
                            );
                        }
                    }
                    $cart['checkout_step'] = 'cart';
                    break;

                case 'add_one':
                    $product = new product();
                    $product->load($_REQUEST['product']);
                    for($i = 0; $i < count($cart['lines']); $i++)
                    {
                        if($cart['lines'][$i]['id'] == $_REQUEST['product'])
                        {
                            if( !isset($_REQUEST['ta']) ||
                                (isset($_REQUEST['ta']) && $_REQUEST['ta'] == $cart['lines'][$i]['timestamp_added'])
                            )
                            {
                                $cart['lines'][$i]['quantity'] = $cart['lines'][$i]['quantity'] + 1;
                            }
                        }
                    }
                    $cart['checkout_step'] = 'cart';
                    break;

                case 'remove_one':
                    for($i = 0; $i < count($cart['lines']); $i++)
                    {
                        if($cart['lines'][$i]['id'] == $_REQUEST['product'])
                        {
                            if( !isset($_REQUEST['ta']) ||
                                (isset($_REQUEST['ta']) && $_REQUEST['ta'] == $cart['lines'][$i]['timestamp_added'])
                            )
                            {
                                $cart['lines'][$i]['quantity'] = $cart['lines'][$i]['quantity'] - 1;
                                if($cart['lines'][$i]['quantity'] <= 0)
                                    unset($cart['lines'][$i]);
                            }
                        }
                    }
                    $cart['checkout_step'] = 'cart';
                    break;

                case 'update_qty':
                    for($i = 0; $i < count($cart['lines']); $i++)
                    {
                        if($cart['lines'][$i]['id'] == $_REQUEST['product'])
                        {
                            if( !isset($_REQUEST['ta']) ||
                                (isset($_REQUEST['ta']) && $_REQUEST['ta'] == $cart['lines'][$i]['timestamp_added'])
                            )
                            {
                                $cart['lines'][$i]['quantity'] = $_REQUEST['qty'];
                                if($cart['lines'][$i]['quantity'] <= 0)
                                    unset($cart['lines'][$i]);
                            }
                        }
                    }
                    $cart['checkout_step'] = 'cart';
                    break;

                case 'remove_product':
                    $product = new product();
                    $product->load($_REQUEST['product']);
                    for($i = 0; $i < count($cart['lines']); $i++)
                    {
                        if($cart['lines'][$i]['id'] == $product->id)
                        {
                            if( !isset($_REQUEST['ta']) ||
                                (isset($_REQUEST['ta']) && $_REQUEST['ta'] == $cart['lines'][$i]['timestamp_added'])
                            )
                                unset($cart['lines'][$i]);
                        }
                    }
                    $cart['checkout_step'] = 'cart';
                    break;

                case 'apply_coupon':
                    // check coupon
                    $coupon_code = trim($_REQUEST['order_coupon']);
                    $coupon = coupon::find($coupon_code);

                    if(!$coupon)
                    {
                        $cart['coupon_error'] = t(745, "Invalid or nonexistent coupon");
                    }
                    else if($coupon)
                    {
                        $redeemable = $coupon->redeemable($cart, $webuser->id);

                        if(!$redeemable)
                        {
                            $cart['coupon_error'] = t(746, "Non-redeemable coupon");
                        }
                        else
                        {
                            // apply coupon
                            $cart['coupon_code'] = $coupon_code;
                            $cart['coupon'] = $coupon->id;
                            $cart['coupon_error'] = "";
                        }
                    }

                    // coupon is now accepted or rejected
                    // recalc cart and update session (done later in this function)
                    break;

                case 'remove_coupon':
                    // apply coupon
                    $cart['coupon'] = 0;
                    $cart['coupon_code'] = "";
                    $cart['coupon_error'] = "";
                    $cart['coupon_amount'] = 0;
                    $cart['coupon_data'] = null;
                    break;

                case 'checkout':
                    // check current step: cart, identification, address, shipping, summary, payment, notification
                    if(empty($cart['checkout_step']) || $_POST['action'] == 'checkout')
                    {
                        $cart['checkout_step'] = 'cart';
                    }

                    // redirect to checkout page, only if there are products in the cart
                    if(!empty($cart['products']))
                    {
                        $cart['checkout_step'] = 'identification';
                    }

                    $session['cart'] = $cart;

                    $checkout_url = nvweb_source_url('theme', 'checkout');
                    nvweb_clean_exit($checkout_url);
                    break;

                default:
                    // nothing to do
                    break;
            }

            // finally, return the updated cart information
            $session['cart'] = nvweb_cart_update($cart);

            if($is_ajax)
            {
                $out = json_encode($session['cart']);
            }
            break;

        case 'cart_url':
        case 'url':
            $out = nvweb_source_url('theme', 'cart');
            break;

        case 'checkout_url':
            $out = nvweb_source_url('theme', 'checkout');
            break;

        case 'products_count':
            $out = $cart['products'];
            break;

        case 'products_quantity':
            $out = $cart['quantity'];
            break;

        case 'subtotal':
            $out = core_price2string($cart['subtotal'], $website->currency);
            break;

        case 'steps':
            $out = nvweb_cart_steps();
            break;

        case 'checkout':

            switch($cart['checkout_step'])
            {
                case 'cart':
                    $cart_url = nvweb_source_url('theme', 'cart');
                    nvweb_clean_exit($cart_url);
                    break;

                case 'identification':
                    $out = nvweb_cart_identification_page($cart);
                    break;

                case 'address':
                    $out = nvweb_cart_address_page($cart);
                    break;

                case 'shipping':
                    $out = nvweb_cart_shipping_page($cart);
                    break;

                case 'summary':
                    $out = nvweb_cart_summary_page($cart);
                    break;

                case 'payment':
                    $order = new order();
                    $order_exists = !empty($cart['order_id']);

                    if($order_exists)
                    {
                        $order->load($cart['order_id']);

                        // the order may be already created but still not paid
                        // and the user may have changed the payment form
                        if(!$order->payment_done)
                        {
                            $order->payment_method = $cart['payment_method'];
                            $order->save();
                        }
                    }
                    else
                    {
                        // in this step the order is set as confirmed
                        // only remaining to be paid
                        $order = order::create_from_cart($cart);
                        $order->save();
                        $cart['order_id'] = @$order->id;
                        $session['cart'] = $cart;

                        // notify order creation
                        $order->send_customer_order_creation();
                    }

                    if(empty($order->id))
                    {
                        // error loading/creating the order
                        throw new Exception($DB->get_last_error());
                    }
                    else
                    {
                        // display payment form or information
                        $out = nvweb_cart_payment_page($order, $order_exists);
                    }
                    break;

                case 'payment_failed':
                    $order = new order();
                    $order->load($cart['order_id']);
                    $out = nvweb_cart_payment_failed($order);
                    break;

                case 'payment_done':
                    $order = new order();
                    $order->load($cart['order_id']);
                    $out = nvweb_cart_payment_done($order);

                    // remove cart, order creation process is completed
                    unset($cart);
                    unset($session['cart']);
                    break;
            }
            break;

        case 'summary':
            $out = nvweb_cart_view_summary($cart, $vars['mode']);
            break;

        case 'view':
        case 'review':
        default:
            $out = nvweb_cart_view($cart, $vars['mode']);
            break;
    }

    return $out;
}

function nvweb_cart_update($old_cart)
{
    global $webuser;
    global $current;

    $current['pagecache_enabled'] = false;

    $cart = array();

    $cart['last_updated'] = core_time();
    $cart['currency'] = $old_cart['currency'];
    $cart['currency_symbol'] = product::currencies($old_cart['currency'], true);
    $cart['checkout_step'] = $old_cart['checkout_step'];
    $cart['products'] = count($old_cart['lines']);
    $cart['lines'] = array();
    $cart['coupon'] = null;
    $cart['shipping_price'] = null;
    $cart['weight'] = null;
    $cart['weight_unit'] = $old_cart['weight_unit'];
    $cart['size_unit'] = $old_cart['size_unit'];
    $cart['customer'] = $old_cart['customer'];
    $cart['address_shipping'] = $old_cart['address_shipping'];
    $cart['address_billing'] = $old_cart['address_billing'];
    $cart['customer_notes'] = $old_cart['customer_notes'];
    $cart['shipping_method'] = $old_cart['shipping_method'];
    $cart['shipping_rate'] = $old_cart['shipping_rate'];
    $cart['order_id'] = $old_cart['order_id'];
    $cart['taxes_breakdown'] = array();

    $quantity = 0;
    $subtotal = 0;
    $subtotal_without_taxes = 0;
    $subtotal_taxes_amount = 0;
    $weight = 0;
    $taxes_breakdown = array();

    foreach($old_cart['lines'] as $old_line)
    {
        if(is_null($old_line))
        {
            continue;
        }

        $line = array(
            'id' => $old_line['id'], // product id
            'timestamp_added' => $old_line['timestamp_added'],
            'sku' => $old_line['sku'],
            'name' => $old_line['name'],
            'option' => $old_line['option'], // future use
            'quantity' => floatval($old_line['quantity']),
            'currency' => $old_line['currency'],
            'price' => 0,
            'coupon_amount' => 0,
            'original_price' => 0,
            'base_price' => 0,
            'tax_value' => 0,
            'base_price_tax_amount' => 0,
            'subtotal_without_taxes' => 0,
            'subtotal_tax_amount' => 0,
            'subtotal' => 0,
            'subtotal_with_taxes_without_coupon' => 0
        );

        $product = new product();
        $product->load($old_line['id']);

        // TODO: get price based on quantity, price list, etc. (warning! quantity may be decimal, must be rounded!)
        // note, if there is a coupon, we'll have to recalculate all line prices later
        $price = $product->get_price();

        $line['price'] = $price['current'];
        $line['original_price'] = $price['old']; // tax included
        $line['base_price'] = $price['base_price'];
        $line['tax_value'] = $price['tax_value'];
        $line['base_price_tax_amount'] = $price['tax_amount'];
        $line['subtotal_without_taxes'] = round($price['base_price'] * $line['quantity'], 2);
        $line['subtotal_tax_amount'] = round($price['tax_amount'] * $line['quantity'], 2);
        $line['subtotal'] = round($line['subtotal_without_taxes'] + $line['subtotal_tax_amount'], 2);
        $line['subtotal_with_taxes_without_coupon'] = $line['subtotal'];

        $cart['lines'][] = $line;

        $quantity = $quantity + $line['quantity'];
        $subtotal = $subtotal + $line['subtotal'];
        $subtotal_without_taxes = $subtotal_without_taxes + $line['subtotal_without_taxes'];
        $weight = $weight + ($product->weight_in_grams() * $line['quantity']);
        $subtotal_taxes_amount = $subtotal_taxes_amount + $line['subtotal_tax_amount'];
        $taxes_breakdown[$price['tax_value']] = @floatval($taxes_breakdown[$price['tax_value']]) + $line['subtotal_tax_amount'];

        unset($product);
    }

    $cart['subtotal_without_taxes'] = $subtotal_without_taxes;
    $cart['quantity'] = $quantity;
    $cart['subtotal'] = $subtotal;
    $cart['subtotal_taxes_amount'] = $subtotal_taxes_amount;
    $cart['weight'] = $weight;

    $cart['shipping_price'] = 0;

    if(!empty($cart['shipping_method']))
    {
        $sm = new shipping_method();
        $sm->load($cart['shipping_method']);

        $shipping_rate = $sm->calculate(
            $cart['address_shipping']['country'],
            $cart['address_shipping']['region'],
            $cart['weight'],
            $cart['subtotal']
        );

        $cart['shipping_carrier'] = $shipping_rate->dictionary[$current['lang']]['title'];
        $cart['shipping_price_without_taxes'] = round($shipping_rate->cost->value, 2);
        $cart['shipping_tax_value'] = ($shipping_rate->cost->tax->class=='custom'? $shipping_rate->cost->tax->value : 0);
        $cart['shipping_tax_amount'] = $cart['shipping_price_without_taxes'] * ($cart['shipping_tax_value'] / 100);
        $cart['shipping_price'] = $cart['shipping_price_without_taxes'] + $cart['shipping_tax_amount'];

        $cart['shipping_method_data'] = json_encode($sm);
    }

    // apply coupon, if any
    $cart['coupon_code'] = $old_cart['coupon_code'];
    $cart['coupon_error'] = $old_cart['coupon_error'];
    $cart['coupon_amount'] = 0;
    $cart['coupon_data'] = null;

    if(!empty($old_cart['coupon']))
    {
        $coupon = new coupon();
        $coupon->load($old_cart['coupon']);
        if( $coupon->redeemable($cart, $webuser->id) )
        {
            $cart['coupon'] = $coupon->id;
            $cart['coupon_data'] = json_encode($coupon);

            // apply coupon discount
            switch($coupon->type)
            {
                case 'discount_percentage':
                    // all cart & line prices must be recalculated
                    $cart['subtotal_before_coupon'] = $cart['subtotal'];
                    $cart['subtotal_without_taxes'] = 0;
                    $cart['subtotal'] = 0;
                    $cart['subtotal_taxes_amount'] = 0;
                    $cart['coupon_amount'] = 0;

                    for($l=0; $l < count($cart['lines']); $l++)
                    {
                        // for each line and product we have to calculate the price following this procedure:
                        // apply the discount to each unit of the product including tax: old price: 12e, new price: 6e (tax included!)
                        // calculate the base price calculating and taking off the tax: 6e (10% tax included) = 5.45 (6 / 1.10) + 0.55 (6 - 5.45)
                        // calculate the line price based on the quantity requested and the new price

                        $line = $cart['lines'][$l];
                        $subtotal_without_taxes_without_discount = $line['subtotal_without_taxes'];

                        $price_without_discount = $line['price'];
                        $price_with_discount = round($line['price'] - ($line['price'] * ($coupon->discount_value/100)), 2);
                        $base_price_with_discount = round($price_with_discount / (1 + $line['tax_value'] / 100), 2);
                        $base_tax_with_discount = round($price_with_discount - $base_price_with_discount, 2);

                        $line['coupon_unit'] = round($line['price'] - $price_with_discount, 2);
                        $line['coupon_amount'] = round($line['coupon_unit'] * $line['quantity'], 2);

                        $line['base_price'] = $base_price_with_discount;
                        $line['base_price_tax_amount'] = $base_tax_with_discount;

                        $line['subtotal_without_taxes'] = round(($base_price_with_discount * $line['quantity']), 2);
                        $line['subtotal_tax_amount'] = round(($base_tax_with_discount * $line['quantity']), 2);
                        $line['subtotal'] = round($line['subtotal_without_taxes'] + $line['subtotal_tax_amount'], 2);
                        $line['subtotal_with_taxes_without_coupon'] = round($price_without_discount * $line['quantity'], 2);
                        $cart['lines'][$l] = $line;

                        $cart['subtotal'] = $cart['subtotal'] + $line['subtotal'];
                        $cart['subtotal_without_taxes'] = $cart['subtotal_without_taxes'] + $line['subtotal_without_taxes'];
                        $cart['subtotal_taxes_amount'] = $cart['subtotal_taxes_amount'] + $line['subtotal_tax_amount'];
                        $cart['coupon_amount'] = $cart['coupon_amount'] + $line['coupon_amount'];
                    }
                    break;

                case 'discount_amount':
                    $cart['subtotal_before_coupon'] = $cart['subtotal'];
                    $cart['subtotal'] = 0;
                    $cart['subtotal_without_taxes'] = 0;
                    $cart['subtotal_taxes_amount'] = 0;
                    $cart['coupon_amount'] = 0;

                    $coupon_amount_left = $coupon->discount_value;

                    for($l=0; $l < count($cart['lines']); $l++)
                    {
                        $line = $cart['lines'][$l];

                        if($l==(count($cart['lines'])-1)) // last order line
                        {
                            $subtotal_without_taxes_without_discount = $line['subtotal_without_taxes'];

                            $line['coupon_unit'] = round($coupon_amount_left / $line['quantity'], 2);
                            $line['coupon_amount'] = round($line['coupon_unit'] * $line['quantity'], 2);

                            $price_without_discount = $line['price'];
                            $price_with_discount = round($line['price'] - $line['coupon_unit'], 2);
                            $base_price_with_discount = round($price_with_discount / (1 + $line['tax_value'] / 100), 2);
                            $base_tax_with_discount = round($price_with_discount - $base_price_with_discount, 2);

                            $line['base_price'] = $base_price_with_discount;
                            $line['base_price_tax_amount'] = $base_tax_with_discount;

                            $line['subtotal_without_taxes'] = round(($base_price_with_discount * $line['quantity']), 2);
                            $line['subtotal_tax_amount'] = round(($base_tax_with_discount * $line['quantity']), 2);
                            $line['subtotal'] = round($line['subtotal_without_taxes'] + $line['subtotal_tax_amount'], 2);
                            $line['subtotal_with_taxes_without_coupon'] = round($price_without_discount * $line['quantity'], 2);

                            $cart['lines'][$l] = $line;

                            $cart['subtotal'] = $cart['subtotal'] + $line['subtotal'];
                            $cart['subtotal_without_taxes'] = $cart['subtotal_without_taxes'] + $line['subtotal_without_taxes'];
                            $cart['subtotal_taxes_amount'] = $cart['subtotal_taxes_amount'] + $line['subtotal_tax_amount'];
                            $cart['coupon_amount'] = $cart['coupon_amount'] + $line['coupon_amount'];

                            $coupon_amount_left = 0;
                        }
                        else
                        {
                            $cart_line_percentage = $line['subtotal'] / $cart['subtotal_before_coupon'];

                            $line['coupon_unit'] = round(($coupon_amount_left * $cart_line_percentage) / $line['quantity'], 2);
                            $line['coupon_amount'] = round($line['coupon_unit'] * $line['quantity'], 2);
                            $coupon_amount_left = round($coupon_amount_left - $line['coupon_amount'], 2);

                            $price_without_discount = $line['price'];
                            $price_with_discount = round($line['price'] - $line['coupon_unit'], 2);
                            $base_price_with_discount = round($price_with_discount / (1 + $line['tax_value'] / 100), 2);
                            $base_tax_with_discount = round($price_with_discount - $base_price_with_discount, 2);

                            $line['base_price'] = $base_price_with_discount;
                            $line['base_price_tax_amount'] = $base_tax_with_discount;

                            $line['subtotal_without_taxes'] = round(($base_price_with_discount * $line['quantity']), 2);
                            $line['subtotal_tax_amount'] = round(($base_tax_with_discount * $line['quantity']), 2);
                            $line['subtotal'] = round($line['subtotal_without_taxes'] + $line['subtotal_tax_amount'], 2);
                            $line['subtotal_with_taxes_without_coupon'] = round($price_without_discount * $line['quantity'], 2);

                            $cart['lines'][$l] = $line;

                            $cart['subtotal'] = $cart['subtotal'] + $line['subtotal'];
                            $cart['subtotal_without_taxes'] = $cart['subtotal_without_taxes'] + $line['subtotal_without_taxes'];
                            $cart['subtotal_taxes_amount'] = $cart['subtotal_taxes_amount'] + $line['subtotal_tax_amount'];
                            $cart['coupon_amount'] = $cart['coupon_amount'] + $line['coupon_amount'];
                        }
                    }
                    break;

                case 'free_shipping':
                    $cart['coupon_amount'] = $cart['shipping_price'];
                    $cart['shipping_price_without_taxes'] = 0;
                    $cart['shipping_tax_value'] = ($shipping_rate->cost->tax->class=='custom'? $shipping_rate->cost->tax->value : 0);
                    $cart['shipping_tax_amount'] = 0;
                    $cart['shipping_price'] = 0;
                    break;

                default:
            }
        }
    }

    $cart['total'] = $cart['subtotal'] + $cart['shipping_price'];
    $cart['taxes_breakdown'] = $taxes_breakdown;

    return $cart;
}

function nvweb_cart_view($cart, $mode='view')
{
    global $website;
    global $html;
    global $current;

    $out = array();

    $cart_url = nvweb_source_url('theme', 'cart');

    $minus_symbol = '&#x2796;';
    $plus_symbol = '&#x2795;';
    $alert_symbol = '';
    $remove_symbol = '';
    $shipping_symbol = '';
    $fontawesome_available = (
        strpos($html,'font-awesome.') ||
        strpos($html,'<i class="fa ')
    );

    if($fontawesome_available)
    {
        $minus_symbol = '<i class="fa fa-fw fa-minus-circle"></i>';
        $plus_symbol = '<i class="fa fa-fw fa-plus-circle"></i>';
        $remove_symbol = '<i class="fa fa-fw fa-trash"></i>';
        $shipping_symbol = '<i class="fa fa-fw fa-info-circle"></i>';
        $alert_symbol = '<i class="fa fa-fw fa-exclamation-triangle"></i>';
    }

    $lines_coupon = !@empty($cart['lines'][0]['coupon_amount']);

    $out[] = '<div class="nv_cart">';
    $out[] = '    <form action="?" method="post">';
    $out[] = '        <table width="100%">';
    $out[] = '            <thead>';
    $out[] = '                <tr>';
    $out[] = '                    <th colspan="2" class="nv_cart_header_product">'.t(198, "Product").'</th>';
    if(!$lines_coupon)
    {
        $out[] = '                    <th width="10%" colspan="2" class="nv_cart_header_price">'.t(741, 'Price').'</th>';
    }
    else
    {
        $out[] = '                    <th width="10%" class="nv_cart_header_price">'.t(741, 'Price').'</th>';
        $out[] = '                    <th width="10%" class="nv_cart_header_discount">'.t(701, 'Discount').'</th>';
    }
    $out[] = '                    <th width="12%" class="nv_cart_header_quantity">'.t(724, 'Quantity').'</th>';
    $out[] = '                    <th width="10%" class="nv_cart_header_subtotal">'.t(685, 'Subtotal').'</th>';
    $out[] = '                </tr>';
    $out[] = '            </thead>';
    $out[] = '            <tbody>';

    if(empty($cart['lines']))
    {
        $out[] = '            <tr>';
        $out[] = '                <td colspan="6" class="nv_cart_empty">'.t(742, "Your shopping cart is empty").'</td>';
        $out[] = '            </tr>';
    }
    else
    {
        $out[] = '          <nv object="list" source="cart">';
        $out[] = '            <tr class="nv_cart_line">';

        $out[] = '                <td valign="top" width="15%" class="nv_cart_line_image">
                                    <a href="{{nvlist source=\'cart\' value=\'url\'}}">
                                        <img src="{{nvlist source=\'cart\' value=\'image\' width=\'310\' height=\'232\'}}" width="100%" />
                                    </a>
                                  </td>';

        $out[] = '                <td valign="top" class="nv_cart_line_title">
                                    <div><a href="{{nvlist source=\'cart\' value=\'url\'}}">{{nvlist source="cart" value="title"}}</a></div>
                                    <div class="nv_cart_line_title_info">
                                        <span class="nv_cart_line_title_info_price">{{nvlist source="cart" value="price" original="true"}}</span>
                                        <small class="nv_cart_line_title_info_remove"><a style="padding-top: 8px; display: inline-block; " href="{{nvlist source=\'cart\' value=\'remove\'}}">' . $remove_symbol . t(35, 'Delete') . '</a></small>
                                    </div>
                                  </td>';

        if(!$lines_coupon)
        {
            $out[] = '            <td valign="top" colspan="2" class="nv_cart_line_price">
                                        <span>{{nvlist source="cart" value="price"}}</span>
                                        <a class="nv_cart_line_price_remove" href="{{nvlist source=\'cart\' value=\'remove\'}}">' . $remove_symbol . t(35, 'Delete') . '</a>
                                  </td>';
        }
        else
        {
            $out[] = '                <td valign="top" class="nv_cart_line_price">
                                        <span>{{nvlist source="cart" value="price"}}</span>
                                        <!--<span class="nv_cart_line_price_coupon_amount_per_unit">{{nvlist source="cart" value="coupon_unit"}}</span>-->
                                        <a class="nv_cart_line_price_remove" href="{{nvlist source=\'cart\' value=\'remove\'}}">' . $remove_symbol . t(35, 'Delete') . '</a>
                                  </td>';

            $coupon_data = json_decode($cart['coupon_data']);
            if($coupon_data->type == 'discount_percentage')
            {
                $out[] = '    <td valign="top" class="nv_cart_line_discount">
                                    <span>{{nvlist source="cart" value="coupon_unit"}}</span>
                                    <em>'.round($coupon_data->discount_value,2).'%</em>
                              </td>';
            }
            else if($coupon_data->type == 'discount_amount')
            {
                $out[] = '    <td valign="top" class="nv_cart_line_discount">
                                    <span>{{nvlist source="cart" value="coupon_unit"}}</span>
                              </td>';
            }
        }

        $out[] = '                <td valign="top" class="nv_cart_line_quantity">';
        if($mode=='view')
        {
            $out[] = '                      <big class="nv_cart_line_quantity_minus"><a href="{{nvlist source=\'cart\' value=\'remove_one\'}}">' . $minus_symbol . '</a></big>';
        }
        $out[] = '                      <strong class="nv_cart_line_quantity_value" data-update_qty-href="{{nvlist source=\'cart\' value=\'update_quantity\'}}">{{nvlist source="cart" value="quantity"}}</strong>';
        if($mode=='view')
        {
            $out[] = '                      <big class="nv_cart_line_quantity_plus"><a href="{{nvlist source=\'cart\' value=\'add_one\'}}">' . $plus_symbol . '</a></big>';
            $out[] = '                      <big class="nv_cart_line_quantity_remove"><a href="{{nvlist source=\'cart\' value=\'remove\'}}">' . $remove_symbol . '</a></big>';
        }
        $out[] = '                </td>';
        $out[] = '                <td valign="top" class="nv_cart_line_total">{{nvlist source="cart" value="subtotal"}}</td>';
        $out[] = '            </tr>';
        $out[] = '          </nv>';
    }
    $out[] = '            </tbody>';

    $out[] = '            <tfoot>';

    if(!empty($cart['coupon']))
    {
        $coupon = new coupon();
        $coupon->load($cart['coupon']);
        if (!isset($coupon->dictionary[$current['lang']]['name']))
        {
            $coupon_info = '<span class="nv_cart_coupon_info">' . $cart['coupon_code'] . '</span>';
        }
        else
        {
            $coupon_info = '<span class="nv_cart_coupon_info"><a href="'.$cart_url.'?action=remove_coupon&coupon='.$coupon->id.'" title="'.t(35, 'Delete').'">' . $remove_symbol . '</a> ' . $coupon->code . '&nbsp;&nbsp;&nbsp;&nbsp;<em>' . $coupon->dictionary[$current['lang']]['name'] . '</em></span>';
        }

        $coupon_amount = "";

        if(!empty($cart['subtotal_before_coupon']))
        {
            /* may lead the customer to error, as the discount is per unit, not per subtotal
            $out[] = '            <tr class="nv_cart_subtotal_before_coupon">';
            $out[] = '                <td colspan="5"></td>';
            $out[] = '                <td colspan="1" style="text-align: right;">
                                         <span>' . core_price2string($cart['subtotal_before_coupon'], $website->currency) . '</span>
                                      </td>';
            $out[] = '            </tr>';
            */

            $coupon_amount = core_price2string($cart['coupon_amount'], $website->currency);
        }


        $out[] = '            <tr class="nv_cart_coupon">';
        $out[] = '                <td colspan="5" style="text-align: right;" class="nv_cart_coupon_information">'.t(788, "Discounts applied").$coupon_info.'</td>';
        $out[] = '                <td colspan="1" style="text-align: right;" class="nv_cart_coupon_amount"><span>'.$coupon_amount.'</span></td>';
        $out[] = '            </tr>';
    }

    $out[] = '                <tr class="nv_cart_subtotal">';
    $out[] = '                    <td colspan="5" style="text-align: right;" class="nv_cart_subtotal_information" title="'.t(743, "The amount excludes shipping, which is applied at checkout.").'">'.$shipping_symbol.'<span>'.t(791, "Without shipping").'</span></td>';
    $out[] = '                    <td colspan="1" style="text-align: right;" class="nv_cart_subtotal_amount">
                                    <big>'.core_price2string($cart['subtotal'], $website->currency).'</big>                              
                                  </td>';
    $out[] = '                </tr>';
    $out[] = '            </tfoot>';
    $out[] = '        </table>';

    if(!empty($cart['lines']))
    {
        $out[] = '    <div class="nv_cart_footer_actions">';
        $out[] = '        <div class="nv_cart_footer_coupons">';
        if(!empty($cart['coupon_error']) && !empty($_REQUEST['order_coupon']))
        {
            $out[] = '                <div class="nv_cart_coupon_error">'.$alert_symbol.$cart['coupon_error'].'</div>';
        }
        if($mode == 'view')
        {
            $out[] = '            <input type="text" placeholder="' . t(726, "Coupon") . '" name="order_coupon" value="" />';
            $out[] = '            <button type="submit" name="action" value="apply_coupon">' . t(744, "Apply") . '</button>';
        }
        $out[] = '        </div>';
        $out[] = '        <div class="nv_cart_footer_checkout">';
        $out[] = '            <p style="text-align: right;">';
        if($mode == 'view')
        {
            $out[] = '             <a class="button secondary" href="'.$website->absolute_path(true).'">' . t(781, "Continue shopping") . '</a>';
            $out[] = '             <button type="submit" name="action" value="checkout">' . t(782, "Proceed to checkout") . '</button>';
        }
        else if($mode == 'review')
        {
            $out[] = '             <a class="button" href="'.nvweb_cart(array('mode' => 'cart_url')).'">' . t(754, "Modify") . '</a>';
        }
        $out[] = '            </p>';
        $out[] = '        </div>';
        $out[] = '    </div>';
    }

    $out[] = '    </form>';
    $out[] = '</div>';

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'
    );

    $out = implode("\n", $out);
    return $out;
}

function nvweb_cart_view_summary($cart)
{
    global $website;
    global $current;

    $out = array();

    $out[] = '<div class="nv_cart nv_cart_view_summary">';
    $out[] = '        <table width="100%">';
    $out[] = '            <thead>';
    $out[] = '                <tr>';
    $out[] = '                    <th class="nv_cart_header_product">'.t(198, "Product").'</th>';
    $out[] = '                    <th width="12%" class="nv_cart_header_quantity">'.t(724, 'Quantity').'</th>';
    $out[] = '                    <th width="10%" class="nv_cart_header_subtotal">'.t(685, 'Subtotal').'</th>';
    $out[] = '                </tr>';
    $out[] = '            </thead>';
    $out[] = '            <tbody>';

    if(empty($cart['lines']))
    {
        $out[] = '            <tr>';
        $out[] = '                <td colspan="3" style="text-align: center;" class="nv_cart_empty">'.t(742, "Your shopping cart is empty").'</td>';
        $out[] = '            </tr>';
    }
    else
    {
        $out[] = '          <nv object="list" source="cart">';
        $out[] = '            <tr class="nv_cart_line">';
        $out[] = '                <td valign="top" class="nv_cart_line_title">
                                    <div><a href="{{nvlist source=\'cart\' value=\'url\'}}">{{nvlist source="cart" value="title"}}</a></div>
                                  </td>';
        $out[] = '                <td valign="top" class="nv_cart_line_quantity">';
        $out[] = '                      <span class="nv_cart_line_quantity_label">'.t(724, 'Quantity').'</span>';
        $out[] = '                      <span class="nv_cart_line_quantity_value">{{nvlist source="cart" value="quantity"}}</span>';
        $out[] = '                </td>';
        $out[] = '                <td valign="top" class="nv_cart_line_price">{{nvlist source="cart" value="subtotal_with_taxes_without_coupon"}}</td>';
        $out[] = '            </tr>';
        $out[] = '          </nv>';
    }
    $out[] = '            </tbody>';

    $out[] = '            <tfoot>';

    if(!empty($cart['coupon']))
    {
        $coupon = new coupon();
        $coupon->load($cart['coupon']);
        if(!isset($coupon->dictionary[$current['lang']]['name']))
        {
            $coupon_info = '<span class="nv_cart_coupon_info">' . $cart['coupon_code'] . '</span>';
        }
        else
        {
            $coupon_info = '<span class="nv_cart_coupon_info">' . $coupon->code . '&nbsp;&nbsp;&nbsp;&nbsp;<em>' . $coupon->dictionary[$current['lang']]['name'] . '</em></span>';
        }

        $coupon_amount = "-";

        if(!empty($cart['subtotal_before_coupon']))
        {
            $coupon_amount = core_price2string($cart['coupon_amount'], $website->currency);
        }

        $out[] = '            <tr class="nv_cart_coupon">';
        $out[] = '                <td colspan="2" style="text-align: right;" class="nv_cart_coupon_information">'.t(788, "Discounts applied").$coupon_info.'</td>';
        $out[] = '                <td colspan="1" style="text-align: right;" class="nv_cart_coupon_amount"><span>'.$coupon_amount.'</span></td>';
        $out[] = '            </tr>';
    }

    $sm = new shipping_method();
    $sm->load($cart['shipping_method']);
    $shipping_method_title = core_special_chars($sm->dictionary[$current['lang']]['title']);

    $out[] = '            <tr class="nv_cart_shipping_method">';
    $out[] = '                <td colspan="2" style="text-align: right;" class="nv_cart_shipping_method_information">'.t(720, "Shipping method").' <strong>'.$shipping_method_title.'</strong></td>';
    $out[] = '                <td colspan="1" style="text-align: right;" class="nv_cart_shipping_method_price"><span>'.core_price2string($cart['shipping_price'], $website->currency).'</span></td>';
    $out[] = '            </tr>';


    $out[] = '                <tr class="nv_cart_subtotal">';
    $out[] = '                    <td colspan="2" style="text-align: right;">'.t(706, "Total").'</td>';
    $out[] = '                    <td colspan="1" style="text-align: right;" class="nv_cart_subtotal_amount">
                                    <big>'.core_price2string($cart['total'], $website->currency).'</big>                              
                                  </td>';
    $out[] = '                </tr>';
    $out[] = '            </tfoot>';
    $out[] = '        </table>';

    $out[] = '</div>';

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'
    );

    $out = implode("\n", $out);
    return $out;
}

function nvweb_cart_steps()
{
    global $session;
    global $current;

    $step = $session['cart']['checkout_step'];
    if($current['template'] == 'cart')
        $step = 'cart';

    $out = array();
    $out[] = '<div class="nv_order_steps">';
    $out[] = '    <span class="nv_order_step '.($step=='cart'? 'current' : '').'" data-order-step="1">'.t(736, "Cart").'</span>';
    $out[] = '    <span class="nv_order_step '.($step=='identification'? 'current' : '').'" data-order-step="2">'.t(756, "Identification").'</span>';
    $out[] = '    <span class="nv_order_step '.($step=='address'? 'current' : '').'" data-order-step="3">'.t(233, "Address").'</span>';
    $out[] = '    <span class="nv_order_step '.($step=='shipping'? 'current' : '').'" data-order-step="4">'.t(28, "Shipping").'</span>';
    $out[] = '    <span class="nv_order_step '.($step=='summary'? 'current' : '').'" data-order-step="5">'.t(337, "Summary").'</span>';
    $out[] = '    <span class="nv_order_step '.(in_array($step, array('payment', 'payment_done', 'payment_failed'))? 'current' : '').'" data-order-step="6">'.t(757, "Payment").'</span>';
    $out[] = '</div>';

    $out[] = '<div class="nv_order_steps_mobile">';
    switch($step)
    {
        case 'cart':
            $out[] = '<span>1 / 6</span> '.t(736, "Cart");
            break;

        case 'identification':
            $out[] = '<span>2 / 6</span> '.t(756, "Identification");
            break;

        case 'address':
            $out[] = '<span>3 / 6</span> '.t(233, "Address");
            break;

        case 'shipping':
            $out[] = '<span>4 / 6</span> '.t(28, "Shipping");
            break;

        case 'summary':
            $out[] = '<span>5 / 6</span> '.t(337, "Summary");
            break;

        case 'payment':
        case 'payment_failed':
        case 'payment_done':
            $out[] = '<span>6 / 6</span> '.t(757, "Payment");
            break;
    }
    $out[] = '</div>';

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_cart.js"></script>'
    );

    nvweb_after_body('js', 'nv_cart_view_init();');

    $out = implode("\n", $out);
    return $out;
}

function nvweb_cart_identification_page($cart)
{
    global $website;
    global $session;
    global $webuser;
    global $html;

    $sign_in_info = "";
    $sign_in_error = "";
    $sign_up_error = "";

    if(!empty($webuser->id))
    {
        // ignore this step, the webuser is already identified
        $cart['checkout_step'] = 'address';
        $cart['customer'] = $webuser->id;
        $session['cart'] = $cart;
        $checkout_url = nvweb_source_url('theme', 'checkout');
        nvweb_clean_exit($checkout_url);
    }

    // process form, if sent
    if(!empty($_POST))
    {
        switch($_POST["nv_cart_wu_submit"])
        {
            case 'sign_in':
                $emailuser_field = $_POST['nv_cart_wu_sign_in_emailusername'];
                $password_field = $_POST['nv_cart_wu_sign_in_password'];

                $ok = $webuser->authenticate($website->id, $emailuser_field, $password_field);
                if(!$ok)
                {
                    // try authenticating with the email field
                    $ok = $webuser->authenticate_by_email($website->id, $emailuser_field, $password_field);
                }

                if($ok)
                {
                    // success, redirect to the next step
                    $webuser->set_cookie();
                    $cart['customer'] = $webuser->id;
                    $cart['checkout_step'] = 'address';
                    $session['cart'] = $cart;
                    $checkout_url = nvweb_source_url('theme', 'checkout');
                    nvweb_clean_exit($checkout_url);
                }

                $sign_in_error = t(765, "There was an error with your Login/Password combination. Please try again.");
                break;

            case 'forgot_password':
                $ok = nvweb_webuser(array(
                    "mode" => "forgot_password",
                    "email_field" => "nv_cart_wu_sign_in_emailusername",
                    "notify" => "boolean"
                ));

                if($ok)
                {
                    $sign_in_info = t(767, "An e-mail with a confirmation has been sent to your address.");
                }
                else
                {
                    $sign_in_error = t(56, "Unexpected error");
                }
                break;

            case 'sign_up':
                // check if this request really comes from the website and not from a spambot
                if( parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->subdomain.'.'.$website->domain &&
                    parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->domain )
                {
                    return;
                }

                $error = nvweb_webuser(array(
                    "mode" => "sign_up",
                    "email_field" => "nv_cart_wu_sign_up_email",
                    "username_field" => "nv_cart_wu_sign_up_username",
                    "password_field" => "nv_cart_wu_sign_up_password",
                    "conditions_field" => "nv_cart_wu_sign_up_conditions",
                    "callback_url" => nvweb_source_url('theme', 'checkout')
                ));

                if(empty($error))
                {
                    $sign_up_info = t(767, "An e-mail with a confirmation has been sent to your address.");
                }
                else
                {
                    $sign_up_error = $error;
                }

                break;

            case 'purchase_without_account':
                // TODO: check if the shop allows orders for guests
                $cart['customer'] = 'guest';
                $cart['checkout_step'] = 'address';
                $session['cart'] = $cart;
                $checkout_url = nvweb_source_url('theme', 'checkout');
                nvweb_clean_exit($checkout_url);
                break;

            default:
                break;
        }
    }

    $fontawesome_available = (
        strpos($html,'font-awesome.') ||
        strpos($html,'<i class="fa ')
    );

    $sign_in_symbol = '';
    $sign_up_symbol = '';
    $pwa_symbol = '';
    $purchase_conditions_link = nvweb_prepare_link($website->shop_purchase_conditions_path);

    if($fontawesome_available)
    {
        $sign_in_symbol = '<i class="fa fa-user"></i> ';
        $sign_up_symbol = '<i class="fa fa-user-plus"></i> ';
        $pwa_symbol = '<i class="fa fa-angle-double-right "></i> ';
    }

    $out = array();
    $out[] = '<div class="nv_cart_identification_form">';
    $out[] = '    <form action="?mode=identification" id="nv_cart_identification_form" method="post">';
    $out[] = '        <div class="nv_cart-flex-sb">
                          <div>
                              <h3>'.$sign_in_symbol.t(758, "Sign in").'</h3>
                              <input type="hidden" name="nv_cart_wu_submit" value="" />
                              <div>
                                  <label>'.t(760, "E-mail or username").'</label>
                                  <input type="text" name="nv_cart_wu_sign_in_emailusername" value="" />
                              </div>
                              <div>
                                  <label>'.t(2, "Password").'</label>                                  
                                  <input type="password" name="nv_cart_wu_sign_in_password" value="" />
                                  <small style="float: right;">
                                        <a href="#" data-action="forgot_password">'.t(407, "Forgot password?").'</a>
                                  </small>
                              </div>
                              <div class="nv_cart-sign_in_info_message">                                   
                                   <p class="custom_message">'.$sign_in_info.'</p>
                              </div>
                              <div class="nv_cart-sign_in_error_message">
                                   <p class="forgot_password_missing_username">'.t(766, "Please enter your e-mail address in the username field and try again.").'</p>
                                   <p class="custom_message">'.$sign_in_error.'</p>
                              </div>
                              <div>
                                  <button class="nv_cart_wu_submit_btn" data-action="sign_in">'.t(3, "Enter" ).'</button>
                              </div>
                          </div>
                          <div>
                              <h3>'.$sign_up_symbol.t(759, "Sign up").'</h3>
                              <div>
                                  <label>'.t(44, "E-Mail").'</label>
                                  <input type="text" name="nv_cart_wu_sign_up_email" value="'.core_special_chars($_POST['nv_cart_wu_sign_up_email']).'" />
                              </div>
                              <div>
                                  <label>'.t(1, "User").' ('.t(764, "optional").')</label>
                                  <input type="text" name="nv_cart_wu_sign_up_username" value="'.core_special_chars($_POST['nv_cart_wu_sign_up_username']).'" />
                              </div>
                              <div>
                                  <label>'.t(2, "Password").'</label>
                                  <input type="password" name="nv_cart_wu_sign_up_password" value="" />
                              </div>                              
                              <div>                                  
                                  <input type="checkbox" name="nv_cart_wu_sign_up_conditions" id="nv_cart_wu_sign_up_conditions" value="1" />
                                  <label for="nv_cart_wu_sign_up_conditions">'.
                                        t(763,
                                          'I acknowledge and accept the <a href="{link}" target="_blank">purchase conditions</a>',
                                            array('{link}' => $purchase_conditions_link)
                                        ).
                                 '</label>
                              </div>
                              <div class="nv_cart-sign_up_info_message">                                   
                                   <p class="custom_message">'.$sign_up_info.'</p>
                              </div>
                              <div class="nv_cart-sign_up_error_message">
                                   <p class="custom_message">'.$sign_up_error.'</p>
                              </div>
                              <div>
                                  <button class="nv_cart_wu_submit_btn" data-action="sign_up">'.t(759, "Sign up").'</button>
                              </div>
                          </div>
                      </div>
                      <hr />
                      <div>
                          <h3>'.$pwa_symbol.t(761, "Purchase without account").'</h3>
                          <p>'.t(762, "If you don't want to keep your own user account on our website, you can place the order as a guest.").'</p>
                          <div>
                              <button class="nv_cart_wu_submit_btn" data-action="purchase_without_account">'.t(152, "Continue").'</button>                            
                          </div>
                      </div>
                  </form>
              </div>';

    $out = implode("\n", $out);

    // add jQuery if has not already been loaded in the template
    if(strpos($html, 'jquery')===false)
        $out[] = '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>';

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_cart.js"></script>'
    );

    nvweb_after_body('js', 'nv_cart_identification_init()');

    return $out;
}

function nvweb_cart_address_page($cart)
{
    global $webuser;
    global $session;

    $cart_url = nvweb_source_url('theme', 'cart');
    $checkout_url = nvweb_source_url('theme', 'checkout');

    if(empty($cart['customer']))
    {
        nvweb_clean_exit($cart_url);
    }

    $customer_username = $webuser->username;
    if($cart['customer'] == 'guest')
    {
        $customer_username = t(719, "Guest");
    }

    $billing_same_as_shipping = true;

    // process form, if sent
    if(!empty($_POST))
    {
        $address_shipping = array(
            'name'     => trim($_POST['order_shipping_name']),
            'nin'      => trim($_POST['order_shipping_nin']), // National identification number
            'company'  => trim($_POST['order_shipping_company']),
            'address'  => trim($_POST['order_shipping_address']),
            'location' => trim($_POST['order_shipping_location']),
            'zipcode'  => trim($_POST['order_shipping_zipcode']),
            'country'  => trim($_POST['order_shipping_country']),
            'region'   => trim($_POST['order_shipping_region']),
            'email'    => trim($_POST['order_shipping_email']),
            'phone'    => trim($_POST['order_shipping_phone'])
        );

        if(isset($_POST['order_billing_same_as_shipping']) && $_POST['order_billing_same_as_shipping']=='1')
        {
            $address_billing = $address_shipping;
        }
        else
        {
            $billing_same_as_shipping = false;
            $address_billing = array(
                'name'     => trim($_POST['order_billing_name']),
                'nin'      => trim($_POST['order_billing_nin']),
                'company'  => trim($_POST['order_billing_company']),
                'address'  => trim($_POST['order_billing_address']),
                'location' => trim($_POST['order_billing_location']),
                'zipcode'  => trim($_POST['order_billing_zipcode']),
                'country'  => trim($_POST['order_billing_country']),
                'region'   => trim($_POST['order_billing_region']),
                'email'    => trim($_POST['order_billing_email']),
                'phone'    => trim($_POST['order_billing_phone'])
            );
        }

        // verify fields, then save them and continue or show an error message
        // required fields: name, address, location, zipcode, country, email, phone
        $errors = array();
        if( empty($address_shipping['name']) ||
            empty($address_shipping['address']) ||
            empty($address_shipping['location']) ||
            empty($address_shipping['zipcode']) ||
            empty($address_shipping['country']) ||
            empty($address_shipping['email']) ||
            empty($address_shipping['phone'])
            )
        {
            $errors[] = '['.t(716, "Shipping address").'] '.t(444, "You left some required fields blank.");
        }

        // validate email address
        if( !filter_var($address_shipping['email'], FILTER_VALIDATE_EMAIL) )
        {
            $errors[] = '['.t(716, "Shipping address").'] '.t(768, "Invalid e-mail address").': '.$address_shipping['email'];
        }

        if( !$billing_same_as_shipping )
        {
            if( empty($address_billing['name']) ||
                empty($address_billing['address']) ||
                empty($address_billing['location']) ||
                empty($address_billing['zipcode']) ||
                empty($address_billing['country']) ||
                empty($address_billing['email']) ||
                empty($address_billing['phone'])
            )
            {
                $errors[] = '['.t(717, "Billing address").'] '.t(444, "You left some required fields blank.");
            }

            if( !filter_var($address_shipping['email'], FILTER_VALIDATE_EMAIL) )
            {
                $errors[] = '['.t(717, "Billing address").'] '.t(768, "Invalid e-mail address").': '.$address_billing['email'];
            }
        }

        if(empty($errors))
        {
            // all set! save the information and go to next step
            $cart['address_shipping']  = $address_shipping;
            $cart['address_shipping']  = $address_billing;
            $cart['checkout_step']     = 'shipping';
            $session['cart'] = $cart;
            nvweb_clean_exit($checkout_url);
        }
    }
    else
    {
        // get default address values

        // get the last order data made by this customer
        $addresses = order::get_addresses($webuser->id);

        // TODO: allow selecting a different address from the list of previously used
        // right now, we default using the most recently used, if any
        $address_shipping = array(
            'name' => $webuser->fullname,
            'nin' => $webuser->nin,
            'company' => $webuser->company,
            'address' => $webuser->address,
            'location' => $webuser->location,
            'zipcode' => $webuser->zipcode,
            'country' => $webuser->country,
            'region' => $webuser->region,
            'email' => $webuser->email,
            'phone' => $webuser->phone
        );

        if(!empty($addresses))
        {
            $address_shipping = $addresses[0];
        }

        $billing_same_as_shipping = true;
    }

    if(!empty($errors))
    {
        $errors = array_map(function($v) { return '<span>'.$v.'</span><br />'; }, $errors);
        array_unshift($errors, '<div class="nv_cart_errors_title">'.t(740, "Error").'</div>');
        $errors = '<div class="nv_cart_errors">'.implode("\n", $errors).'</div>';
    }
    else
    {
        $errors = "";
    }

    $out = array();
    $out[] = '<div class="nv_cart_address_form">';
    $out[] = '    <div class="nv_cart_signed_in_as"><span>'.$customer_username.'</span> <a href="'.$cart_url.'?webuser_signout" title="'.t(5, "Log out").'">'.'&#11198;'.'</a></div>';
    $out[] = $errors;
    $out[] = '    <form action="?mode=address" method="post">';
    $out[] = '      <h3>'.t(716, "Shipping address").'</h3>                
                    <div>
                        <div>
                            <label>'.t(752, "Full name").'</label>
                            <input type="text" name="order_shipping_name" required value="'.core_special_chars($address_shipping['name']).'" />
                        </div>                        
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(778, "National identification number").'</label>
                            <input type="text" name="order_shipping_nin" value="'.core_special_chars($address_shipping['nin']).'" />
                        </div>
                        <div>
                            <label>'.t(592, "Company").'</label>
                            <input type="text" name="order_shipping_company" value="'.core_special_chars($address_shipping['company']).'" />
                        </div>
                    </div>
                    <div>
                        <label>'.t(233, "Address").'</label>
                        <input type="text" name="order_shipping_address" required value="'.core_special_chars($address_shipping['address']).'" />
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(319, "Location").'</label>
                            <input type="text" name="order_shipping_location" required value="'.core_special_chars($address_shipping['location']).'" />
                        </div>
                        <div>
                            <label>'.t(318, "Zip code").'</label>
                            <input type="text" name="order_shipping_zipcode" value="'.core_special_chars($address_shipping['zipcode']).'" />
                        </div>
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(224, "Country").'</label>
                            <nv object="nvweb" name="forms" mode="country_field" required field_name="order_shipping_country" default="'.$address_shipping['country'].'" />
                        </div>
                        <div>
                            <label>'.t(473, "Region").'</label>
                            <nv object="nvweb" name="forms" mode="country_region_field" field_name="order_shipping_region" country_field="order_shipping_country" default="'.$address_shipping['region'].'" />
                        </div>
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(44, "E-Mail").'</label>
                            <input type="text" name="order_shipping_email" required value="'.(empty($address_shipping['email'])? core_special_chars($webuser->email) : core_special_chars($address_shipping['email'])).'" />
                        </div>
                        <div>
                            <label>'.t(320, "Phone").'</label>
                            <input type="text" name="order_shipping_phone" required value="'.core_special_chars($address_shipping['phone']).'" />
                        </div>
                    </div>
                    
                    <h3>'.t(717, "Billing address").'</h3>
                    
                    <input type="checkbox" name="order_billing_same_as_shipping" 
                           id="order_billing_same_as_shipping" value="1" 
                           '.($billing_same_as_shipping? 'checked="checked"' : '').'/>
                    <label for="order_billing_same_as_shipping">'.t(751, "Same as shipping address").'</label>
                    
                    <div class="order_billing_address_wrapper">
                    
                        <div>
                            <div>
                                <label>'.t(752, "Full name").'</label>
                                <input type="text" name="order_billing_name" value="'.core_special_chars($address_billing['name']).'" />
                            </div>                            
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(778, "National identification number").'</label>
                                <input type="text" name="order_billing_nin" value="'.core_special_chars($address_billing['nin']).'" />
                            </div>
                            <div>
                                <label>'.t(592, "Company").'</label>
                                <input type="text" name="order_billing_company" value="'.core_special_chars($address_billing['company']).'" />
                            </div>
                        </div>
                        <div>
                            <label>'.t(233, "Address").'</label>
                            <input type="text" name="order_billing_address" value="'.core_special_chars($address_billing['address']).'" />
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(319, "Location").'</label>
                                <input type="text" name="order_billing_location" value="'.core_special_chars($address_billing['location']).'" />
                            </div>
                            <div>
                                <label>'.t(318, "Zip code").'</label>
                                <input type="text" name="order_billing_zipcode" value="'.core_special_chars($address_billing['zipcode']).'" />
                            </div>
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(224, "Country").'</label>
                                <nv object="nvweb" name="forms" mode="country_field" field_name="order_billing_country" default="'.core_special_chars($address_billing['country']).'" />
                            </div>
                            <div>
                                <label>'.t(473, "Region").'</label>
                                <nv object="nvweb" name="forms" mode="country_region_field" field_name="order_billing_region" country_field="order_billing_country"  default="'.core_special_chars($address_billing['region']).'" />
                            </div>
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(44, "E-Mail").'</label>
                                <input type="text" name="order_billing_email" value="'.core_special_chars($address_billing['email']).'" />
                            </div>
                            <div>
                                <label>'.t(320, "Phone").'</label>
                                <input type="text" name="order_billing_phone" value="'.core_special_chars($address_billing['phone']).'" />
                            </div>
                        </div>                        
                    </div>                                     
                    <br />
                    <br />
                    <div>
                        <input type="submit" class="button nv_cart_button_continue" value="'.t(755, "Continue").'" />
                    </div>';

    $out[] = '    </form>';
    $out[] = '</div>';

    $out = implode("\n", $out);

    nvweb_after_body('js', '
        $("input[name=order_billing_same_as_shipping]").on("click change", function()
        {        
            if($(this).is(":checked"))
            {
                $(".order_billing_address_wrapper").slideUp();
            }
            else
            {
                $(".order_billing_address_wrapper").slideDown();
            }                
        });
        
        $("input[name=order_billing_same_as_shipping]").trigger("change"); 
    ');

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'
    );

    return $out;
}

function nvweb_cart_shipping_page($cart)
{
    global $website;
    global $webuser;
    global $session;

    $cart_url = nvweb_source_url('theme', 'cart');
    $checkout_url = nvweb_source_url('theme', 'checkout');

    if(isset($_GET['modify_address']))
    {
        $cart['checkout_step'] = 'address';
        $session['cart'] = $cart;
        nvweb_clean_exit($checkout_url);
    }

    if(!empty($_POST))
    {
        $customer_notes = core_purify_string(trim($_POST['order_notes']));
        $cart['customer_notes']    = $customer_notes;

        list($order_shipping_method, $order_shipping_method_rate) = explode("/", $_POST['order_shipping_method']);
        $cart['shipping_method'] = $order_shipping_method;
        $cart['shipping_rate'] = $order_shipping_method_rate;

        if(!empty($cart['shipping_rate']))
        {
            $cart['checkout_step'] = 'summary';
            $session['cart'] = nvweb_cart_update($cart);
            $checkout_url = nvweb_source_url('theme', 'checkout');
            nvweb_clean_exit($checkout_url);
        }
    }

    $out = array();
    $shipping_methods = shipping_method::get_available();

    if(empty($cart['customer']))
    {
        nvweb_clean_exit($cart_url);
    }

    $customer_username = $webuser->username;
    if($cart['customer'] == 'guest')
    {
        $customer_username = t(719, "Guest");
    }

    $out[] = '    <div class="nv_cart_signed_in_as">
                        <span>'.$customer_username.'</span> 
                        <a href="'.$cart_url.'?webuser_signout" title="'.t(5, "Log out").'">'.'&#11198;'.'</a>
                  </div>';

    $out[] = '<div class="nv_cart_shipping_form">';
    $out[] = '    <form action="?mode=shipping" method="post">';
    $out[] = '      <h3>'.t(720, "Shipping method").'</h3>';

    $shipping_methods_available = 0;

    foreach($shipping_methods as $sm)
    {
        if(!nvweb_object_enabled($sm))
        {
            continue;
        }

        $shipping_rate = $sm->calculate(
            $cart['address_shipping']['country'],
            $cart['address_shipping']['region'],
            $cart['weight'],
            $cart['subtotal']
        );

        if(empty($shipping_rate))
        {
            continue;
        }

        $shipping_methods_available++;

        $shipping_price = $shipping_rate->cost->value;

        // if the customer has applied a "free shipping" coupon, just show zero
        if(!empty($cart['coupon']))
        {
            $coupon = new coupon();
            $coupon->load($cart['coupon']);
            if($coupon->type == 'free_shipping')
            {
                $shipping_price = 0;
            }
            unset($coupon);
        }

        $out[] = '<div class="nv_cart_shipping_method_option" data-shipping_method-id="'.$sm->id.'" data-shipping_method-rate="'.$shipping_rate->id.'" data-shipping_method-cost="'.number_format($shipping_price, 2, '.', '').'">';
        $out[] = '  <div class="nv_cart_shipping_method_option_left">';

        $out[] = '    <div class="nv_cart_shipping_method_option_title">';
        if(!empty($sm->image))
        {
            $out[] = '<img src="'.file::file_url($sm->image, 'inline').'" width="96" />';
        }
        else
        {
            $shipping_method_title = core_special_chars($sm->dictionary[$session['lang']]['title']);
            $out[] = $shipping_method_title;
        }
        $out[] = '    </div>';
        $out[] = '    <p>' . $sm->dictionary[$session['lang']]['description'] . '</p>';
        $out[] = '  </div>';

        $out[] = '  <div class="nv_cart_shipping_method_option_right">';
        if($shipping_price > 0)
        {
            $out[] = '     <div>'.core_price2string($shipping_price, $cart['currency']).'</div>';
        }
        else
        {
            $out[] = '     <div>'.t(699, "Free shipping").'</div>';
        }
        $out[] = '     <input type="radio" name="order_shipping_method" value="'.$sm->id.'/'.$shipping_rate->id.'" />';
        $out[] = '  </div>';

        $out[] = '</div>';
    }

    if($shipping_methods_available > 0)
    {
        $country_name = property::country_name_by_code($cart['address_shipping']['country']);

        $out[] = '    <div class="nv_cart_shipping_order_total" data-order-subtotal="'.number_format($cart['subtotal'], 2, '.', '').'">'.t(706, "Total").': <span>'.core_decimal2string($cart['subtotal']).'</span> '.$cart['currency_symbol'].'</div>';
        $out[] = '        <br />';

        $out[] = '  <div class="nv_cart-flex-sb">';
        $out[] = '      <div>';
        $out[] = '         <h3>'.t(716, "Shipping address").'</h3>';
        $out[] = '         <div class="nv_cart_shipping_address_information"><p>';
        $out[] = '          '.$cart['address_shipping']['name'].'<br />';
        if(!empty($cart['address_shipping']['company']))
        {
            $out[] = '[ '.$cart['address_shipping']['company'].' ]<br />';
        }
        $out[] = '          '.$cart['address_shipping']['address'].'<br />';
        $out[] = '          '.$cart['address_shipping']['zipcode'].' '.$cart['address_shipping']['location'].'<br />';
        if(!empty($cart['address_shipping']['region']))
        {
            $region_name = property::country_region_name_by_code($cart['address_shipping']['region']);
            $out[] = '      ' . $region_name . ', ';
        }
        $out[] = $country_name;
        $out[] = '        <br />';
        $out[] = '        <a href="?modify_address"><small>(' . t(754, "Modify") . ')</small></a>';
        $out[] = '        </div>';
        $out[] = '      </div>';

        $out[] = '      <div>';
        $out[] = '        <h3>'.t(168, "Notes").'</h3>';
        $out[] = '        <textarea rows="4" name="order_notes">'.core_special_chars($cart['customer_notes']).'</textarea>';
        $out[] = '      </div>';

        $out[] = '  </div>';

        $out[] = '        <input type="submit" disabled class="button nv_cart_button_continue" value="'.t(755, "Continue").'" />';
    }
    else
    {
        $cart['checkout_step'] = 'cart';
        $session['cart'] = $cart;
        $out[] = '<div class="nv_cart_errors">'.t(836, "Sorry, there are no shipping methods available for the address provided.").' '.t(837, "The order cannot be placed.").'</div>';
        $out[] = '<a href="'.$cart_url.'" class="button nv_cart_button_cancel">'.t(58, "Cancel").'</a>';
    }

    $out[] = '    </form>';
    $out[] = '</div>';

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_cart.js"></script>'
    );

    nvweb_after_body('js', 'nv_cart_shipping_options_init("'.$website->decimal_separator.'");');

    return implode("\n", $out);
}

function nvweb_cart_summary_page($cart)
{
    global $website;
    global $webuser;
    global $current;
    global $session;

    $out = array();

    $cart_url = nvweb_source_url('theme', 'cart');
    $checkout_url = nvweb_source_url('theme', 'checkout');

    if(empty($cart['customer']))
    {
        nvweb_clean_exit($cart_url);
    }

    if(!empty($_POST))
    {
        if(!empty($_POST['payment_method']))
        {
            $cart['payment_method'] = $_POST['payment_method'][0];
            $cart['checkout_step'] = 'payment';
            $session['cart'] = $cart;
            nvweb_clean_exit($checkout_url);
        }
    }

    $payment_methods = payment_method::get_available();

    $customer_username = $webuser->username;
    if($cart['customer'] == 'guest')
    {
        $customer_username = t(719, "Guest");
    }

    $out[] = '<div class="nv_cart_signed_in_as"><span>'.$customer_username.'</span> <a href="'.$cart_url.'?webuser_signout" title="'.t(5, "Log out").'">'.'&#11198;'.'</a></div>';

    $out[] = '<div class="nv_cart_summary_form">';
    $out[] = '    <form action="?" method="post">';


    // PRODUCTS
    $out[] = '  <div>';
    $out[] = '      <h5>'.t(734, "Order").'</h5>';
    $out[] = '      '.nvweb_cart(array('mode' => 'summary'));
    $out[] = '  </div>';


    $out[] = '  <div class="nv_cart-flex-sb">';

    // PAYMENT METHOD (choose)
    $out[] = '    <div>';
    $out[] = '        <h5>'.t(727, "Payment method").'</h5>';
    $out[] = '        <p>';
    foreach($payment_methods as $pm)
    {
        $payment_method_title = core_special_chars($pm->dictionary[$current['lang']]['title']);

        if(empty($pm->image))
        {
            if(!empty($pm->icon))
            {
                $icon_html = nvweb_content_icon($pm->icon, "fa-lg fa-fw");
                $payment_method_title = $icon_html.' '.$payment_method_title;
            }

            $out[] = '<label><input type="radio" name="payment_method[]" value="' . $pm->id . '" /> ' .
                $payment_method_title .
                '</label>';
        }
        else
        {
            $out[] = '<label><input type="radio" name="payment_method[]" value="' . $pm->id . '" /> ' .
                '<img src="'.file::file_url($pm->image, 'inline').'" title="'.$payment_method_title.'" style="height:24px; width: auto;" />' .
                '</label>';
        }
    }
    $out[] = '        </p>';
    $out[] = '    </div>';

    // SHIPPING INFO
    $country_name = property::country_name_by_code($cart['address_shipping']['country']);
    $out[] = '  <div class="nv_cart-flex-sb">';
    $out[] = '      <div>';
    $out[] = '         <h5>'.t(716, "Shipping address").'</h5>';
    $out[] = '         <div class="nv_cart_shipping_address_information"><p>';
    $out[] = '          '.$cart['address_shipping']['name'].'<br />';
    if(!empty($cart['address_shipping']['company']))
    {
        $out[] = '[ '.$cart['address_shipping']['company'].' ]<br />';
    }
    $out[] = '          '.$cart['address_shipping']['address'].'<br />';
    $out[] = '          '.$cart['address_shipping']['zipcode'].' '.$cart['address_shipping']['location'].'<br />';
    if(!empty($cart['address_shipping']['region']))
    {
        $region_name = property::country_region_name_by_code($cart['address_shipping']['region']);
        $out[] = '      ' . $region_name . ', ';
    }
    $out[] = $country_name;
    $out[] = '        </div>';
    $out[] = '      </div>';

    $out[] = '      <div>';
    $out[] = '        <h5>'.t(717, "Billing address").'</h5>';

    if(empty($cart['address_billing']))
    {
        $out[] = '<small>('.t(751, "Same as shipping address").')</small>';
    }
    else
    {
        $out[] = '          '.$cart['address_billing']['name'].'<br />';
        if(!empty($cart['address_billing']['company']))
        {
            $out[] = '[ '.$cart['address_billing']['company'].' ]<br />';
        }
        $out[] = '          '.$cart['address_billing']['address'].'<br />';
        $out[] = '          '.$cart['address_billing']['zipcode'].' '.$cart['address_shipping']['location'].'<br />';
        if(!empty($cart['address_billing']['region']))
        {
            $region_name = property::country_region_name_by_code($cart['address_shipping']['region']);
            $out[] = '      ' . $region_name . ', ';
        }
        $out[] = $country_name;
    }
    $out[] = '        <br /><br />';
    $out[] = '      </div>';

    $out[] = '  </div>';

    $out[] = '  </div>';


    if(!empty($cart['customer_notes']))
    {
        $out[] = '<div>';
        $out[] = '      <h5>' . t(205, "Comment") . '</h5>';
        $out[] = $cart['customer_notes'];
        $out[] = '<br /><br />';
        $out[] = '  </div>';
    }

    $out[] = '  <br />';

    $out[] = '  <div class="nv_cart_summary_actions">';
    $out[] = '    <button type="submit" name="action" value="confirmation">' . t(753, "Place order") . '</button>';
    $out[] = '    <a class="button secondary" href="'.nvweb_cart(array('mode' => 'cart_url')).'">' . t(754, "Modify") . '</a>';
    $out[] = '  </div>';

    $out[] = '    </form>';
    $out[] = '</div>';

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_cart.js"></script>'
    );

    nvweb_after_body('js', 'nv_cart_summary_init("'.$website->decimal_separator.'");');

    return implode("\n", $out);
}

function nvweb_cart_payment_page($order, $order_exists=false)
{
    global $html;
    global $current;
    global $website;

    $out = array();

    $fontawesome_available = ( strpos($html,'font-awesome.') || strpos($html,'<i class="fa ') );

    if($fontawesome_available)
    {
        $order_created_symbol = '<i class="fa fa-fw fa-check-square-o"></i> ';
        $payment_symbol = '<i class="fa fa-fw fa-id-card"></i> ';
    }

    $out[] = '<div class="nv_cart_order_created">';

    if(!$order_exists) // if the order has just been created, show thank you message
    {
        $out[] = '<div class="nv_cart_order_created_title"><h3>'.$order_created_symbol.t(792, "Order created").'</h3></div>';
        $out[] = '<p class="nv_cart_order_created_thanks">'.t(793, "Thank you! Your order has been received.").'</p>';
    }
    else
    {
        $out[] = '<div class="nv_cart_order_created_title"><h3>'.$order_created_symbol.t(809, "Order summary").'</h3></div>';
    }

    $out[] = '<blockquote class="nv_cart_order_created_summary">';
    $out[] = t(794, "Order reference").': '.$order->reference.'<br />';
    $out[] = t(795, "Order date").': '.core_ts2date($order->date_created, true).'<br />';
    $out[] = t(796, "Order total").': '.core_price2string($order->total, $order->currency);
    $out[] = '</blockquote>';

    if($order->total == 0)
    {
        $out[] = '<p class="nv_cart_order_created_free">'.t(797, "As the order is FREE, we will begin to prepare it for delivery as soon as possible.").'</p>';

        if(!empty($website->shop_customer_account_path))
        {
            $out[] = '<p class="nv_cart_order_created_check_status">'.t(798, "Remember you can always check the status of your order in your user account, or right now clicking the button below.").'</p>';
            $webuser_account_page = nvweb_prepare_link($website->shop_customer_account_path);
            $out[] = '<p class="nv_cart_order_created_view_order"><a class="button" href="'.$webuser_account_page.'?s=orders&oid='.$order->id.'">'.t(799, "View order").'</a></p>';
        }
    }
    else
    {
        $payment_method = new payment_method();
        $payment_method->load($order->payment_method);

        $payment_method_title = core_special_chars($payment_method->dictionary[$current['lang']]['title']);

        $out[] = '<div class="nv_cart_order_created_payment_title"><h3>'.$payment_symbol.t(757, "Payment").'</h3></div>';
        $out[] = '<p class="nv_cart_order_created_payment_method_info">'.t(727, "Payment method").': <span>'.$payment_method_title.'</span></p>';
        $out[] = '<div class="nv_cart_order_created_payment_method_content">'.$payment_method->checkout($order).'</div>';
    }

    $out[] = '</div>';

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_cart.js"></script>'
    );

    return implode("\n", $out);
}

function nvweb_cart_payment_failed($order)
{
    global $html;
    global $current;
    global $session;

    $out = array();

    $checkout_url = nvweb_source_url('theme', 'checkout');

    if(!empty($_POST) && !empty($_POST['payment_method_change']))
    {
        $order->payment_method = intval($_POST['payment_method_change'][0]);
        $order->save();

        $cart = $session['cart'];
        $cart['payment_method'] = $_POST['payment_method_change'][0];
        $cart['checkout_step'] = 'payment';
        $session['cart'] = $cart;
        nvweb_clean_exit($checkout_url);
    }

    $fontawesome_available = ( strpos($html,'font-awesome.') || strpos($html,'<i class="fa ') );

    if($fontawesome_available)
    {
        $order_payment_failed_symbol = '<i class="fa fa-fw fa-exclamation-triangle"></i> ';
    }

    $out[] = '<div class="nv_cart_order_payment_failed_title"><h3>'.$order_payment_failed_symbol.t(805, "Order payment failed").'</h3></div>';
    $out[] = '<p class="nv_cart_order_payment_failed_message">'.t(806, "The payment process could not be completed.").'</p>';
    $out[] = '<p class="nv_cart_order_payment_try_again">'.t(807, "Please try again or choose an alternative payment method.").'</p>';

    $out[] = '<blockquote class="nv_cart_order_created_summary">';
    $out[] = t(794, "Order reference").': '.$order->reference.'<br />';
    $out[] = t(795, "Order date").': '.core_ts2date($order->date_created, true).'<br />';
    $out[] = t(796, "Order total").': '.core_price2string($order->total, $order->currency).'<br />';
    $out[] = '</blockquote>';

    $payment_methods = payment_method::get_available();

    $out[] = '    <form action="?" class="payment_failed_choose_method" method="post">';
    $out[] = '        <h5>'.t(727, "Payment method").'</h5>';
    $out[] = '        <p>';
    foreach($payment_methods as $pm)
    {
        $payment_method_title = core_special_chars($pm->dictionary[$current['lang']]['title']);

        if(empty($pm->image))
        {
            if(!empty($pm->icon))
            {
                $icon_html = nvweb_content_icon($pm->icon, "fa-lg fa-fw");
                $payment_method_title = $icon_html.' '.$payment_method_title;
            }

            $out[] = '<label><input type="radio" name="payment_method_change[]" value="' . $pm->id . '" /> ' .
                 $payment_method_title .
                '</label>';
        }
        else
        {
            $out[] = '<label><input type="radio" name="payment_method_change[]" value="' . $pm->id . '" /> ' .
                '<img src="'.file::file_url($pm->image, 'inline').'" title="'.$payment_method_title.'" style="height:24px; width: auto;" />' .
                '</label>';
        }
    }
    $out[] = '        </p>';
    $out[] = '        <button type="submit">'.t(152, "Continue").'</button>';
    $out[] = '    </form>';


    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_cart.js"></script>'
    );

    nvweb_after_body(
        'js',
        '$("input[name=\'payment_method_change[]\'][value='.$order->payment_method.']").trigger("click");'
    );

    return implode("\n", $out);
}

function nvweb_cart_payment_done($order)
{
    global $html;
    global $current;
    global $website;

    $out = array();

    $fontawesome_available = ( strpos($html,'font-awesome.') || strpos($html,'<i class="fa ') );

    if($fontawesome_available)
    {
        $order_created_symbol = '<i class="fa fa-fw fa-check-square-o"></i> ';
    }

    $payment_method = new payment_method();
    $payment_method->load($order->payment_method);
    $payment_method_title = core_special_chars($payment_method->dictionary[$current['lang']]['title']);

    $out[] = '<div class="nv_cart_order_paid_title"><h3>'.$order_created_symbol.t(802, "Order paid").'</h3></div>';
    $out[] = '<p class="nv_cart_order_paid_thanks">'.t(803, "Thank you! Your payment has been received.").'</p>';
    $out[] = '<p class="nv_cart_order_begin_processing">'.t(804, "We will begin preparing your order for delivery as soon as possible.").'</p>';

    $out[] = '<blockquote class="nv_cart_order_created_summary">';
    $out[] = t(794, "Order reference").': '.$order->reference.'<br />';
    $out[] = t(795, "Order date").': '.core_ts2date($order->date_created, true).'<br />';
    $out[] = t(796, "Order total").': '.core_price2string($order->total, $order->currency).'<br />';
    $out[] = t(727, "Payment method").': '.$payment_method_title.'<br />';
    $out[] = '</blockquote>';

    if(!empty($website->shop_customer_account_path))
    {
        $out[] = '<p class="nv_cart_order_created_check_status">'.t(798, "Remember you can always check the status of your order in your user account, or right now clicking the button below.").'</p>';
        $webuser_account_page = nvweb_prepare_link($website->shop_customer_account_path);
        $out[] = '<p class="nv_cart_order_created_view_order"><a class="button" href="'.$webuser_account_page.'?s=orders&oid='.$order->id.'">'.t(799, "View order").'</a></p>';
    }

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_cart.js"></script>'
    );

    return implode("\n", $out);
}

?>