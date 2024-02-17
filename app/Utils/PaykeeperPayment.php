<?php

namespace App\Utils;
use Illuminate\Support\Facades\Log;

class PaykeeperPayment
{

    public $fiscal_cart = array(); //fz54 cart
    public $order_total = 0; //order total sum
    public $shipping_price = 0; //shipping price
    public $use_taxes = false;
    public $tax_included = true;
    public $use_delivery = false;
    public $delivery_index = -1;
    public $single_item_index = -1;
    public $more_then_one_item_index = -1;
    public $order_params = NULL;
    public $discounts = array();

    public function setOrderParams($order_total = 0, $clientid = "", $orderid = "", $client_email = "",
                                   $client_phone = "", $service_name = "", $form_url = "", $secret_key = "", $vat_sum = 0)
    {
        $this->setOrderTotal($order_total);
        $this->order_params = array(
            "sum" => $order_total,
            "clientid"      => $clientid,
            "orderid"       => $orderid,
            "client_email"  => $client_email,
            "client_phone"  => $client_phone,
            "service_name"  => $service_name,
            "form_url"      => $form_url,
            "secret_key"    => $secret_key,
            "vat_sum"       => $vat_sum,
        );
    }

    public function getOrderParams($value)
    {
        return array_key_exists($value, $this->order_params) ? $this->order_params["$value"] : False;
    }

    public function updateFiscalCart($ftype, $name = "", $price = 0, $quantity = 0, $sum = 0, $tax = "none",$tax_rate=0)
    {
        //update fz54 cart
        if ($ftype === "create") {
            $name = str_replace("\n ", "", $name);
            $name = str_replace("\r ", "", $name);
        }
        $this->fiscal_cart[] = array(
            "name" => $name,
            "price" => $price,
            "quantity" => $quantity,
            "sum" => $sum,
            "tax" => $tax,
            "tax_rate" => $tax_rate
        );
    }

    public function getFiscalCart()
    {
        return $this->fiscal_cart;
    }

    public function setDiscounts($discount_enabled_flag)
    {
        $discount_modifier_value = 1;
        //set discounts
        if ($discount_enabled_flag) {

            if ($this->getFiscalCartSum(false) > 0)
                $discount_modifier_value = ($this->getOrderTotal() - $this->getShippingPrice()) / $this->getFiscalCartSum(false);
            if ($discount_modifier_value < 1) {
                for ($pos = 0; $pos < count($this->getFiscalCart()); $pos++) {//iterate fiscal cart without shipping
                    if ($pos != $this->delivery_index) {
                        $this->fiscal_cart[$pos]["sum"] *= $discount_modifier_value;
                        $this->fiscal_cart[$pos]["price"] = $this->fiscal_cart[$pos]["sum"] / $this->fiscal_cart[$pos]["quantity"];
                    }
                }
            }
        }
    }
    public function vatInclide()
    {

        $vat_sum    =   $this->getOrderParams("vat_sum");
        $cart       =   $this->getFiscalCart();
        $sum=0;
        for($i=0;$i<count($cart);$i++)
        {
            if(+$cart[$i]["tax_rate"]>0 || $cart[$i]["tax"]!="none" || $cart[$i]["tax_rate"]!="vat0")
            {
                //Log::debug("1`111111111111111111111111111");
                $this->fiscal_cart[$i]["price"] += (+$this->fiscal_cart[$i]["price"]/100)*+$cart[$i]["tax_rate"];
                $this->fiscal_cart[$i]["sum"]   = number_format($this->fiscal_cart[$i]["price"]*+$this->fiscal_cart[$i]["quantity"],2,'.','');
            }
            else
            {
                $this->fiscal_cart[$i]["sum"]   =   number_format($this->fiscal_cart[$i]["sum"],2,'.','');
            }
        }
//        echo '<pre>';
//        print_r($this->fiscal_cart);
//        die;
    }
    public function correctPrices()
    {
        $cart       =   $this->getFiscalCart();
        for($i=0;$i<count($cart);$i++)
        {
            $this->fiscal_cart[$i]["price"] = number_format($this->fiscal_cart[$i]["sum"]/$this->fiscal_cart[$i]["quantity"],4,'.','');
        }
    }
    public function correctPrecision()
    {
        //handle possible precision problem
        $fiscal_cart_sum = $this->getFiscalCartSum(true);
        $total_sum = $this->getOrderTotal();
        $diff_sum = $total_sum - $fiscal_cart_sum;
        //debug_info
        //echo "\ntotal: $total_sum - cart: $fiscal_cart_sum - diff: $diff_sum";
        if (abs($diff_sum) <= 0.01) {
            if ($this->getUseDelivery()) { //delivery is used
                $this->correctPriceOfCartItem($diff_sum, count($this->fiscal_cart) - 1);
            } else {
                if ($this->single_item_index >= 0) { //we got single cart element
                    $this->correctPriceOfCartItem($diff_sum, $this->single_item_index);
                } else if ($this->more_then_one_item_index >= 0) { //we got cart element with more then one quantity
                    $this->splitCartItem($this->more_then_one_item_index);
                    //add diff_sum to the last element (just separated) of fiscal cart
                    $this->correctPriceOfCartItem($diff_sum, count($this->fiscal_cart) - 1);
                } else { //we only got cart elements with less than one quantity
                    $modify_value = ($diff_sum > 0) ? $total_sum / $fiscal_cart_sum : $fiscal_cart_sum / $total_sum;
                    for ($pos = 0; $pos < count($this->getFiscalCart()); $pos++) {
                        $this->fiscal_cart[$pos]["sum"] *= $modify_value;
                        $this->fiscal_cart[$pos]["price"] = $this->fiscal_cart[$pos]["sum"] / $this->fiscal_cart[$pos]["quantity"];
                    }
                }
            }
        }
    }

    public function setOrderTotal($value)
    {
        $this->order_total = $value;
    }

    public function getOrderTotal()
    {
        return $this->order_total;
    }

    public function setShippingPrice($value)
    {
        $this->shipping_price = $value;
    }

    public function getShippingPrice()
    {
        return $this->shipping_price;
    }

    public function getPaymentFormType()
    {
        if (strpos($this->order_params["form_url"], "/order/inline") == True)
            return "order";
        else
            return "create";
    }

    public function setUseTaxes()
    {
        $this->use_taxes = True;
    }

    public function getUseTaxes()
    {
        return $this->use_taxes;
    }

    public function setUseDelivery()
    {
        $this->use_delivery = True;
    }

    public function getUseDelivery()
    {
        return $this->use_delivery;
    }

    //$zero_value_as_none: if variable is set, then when tax_rate is zero, tax is equal to none
    public function setTaxes($tax_rate, $zero_value_as_none = true)
    {
        $taxes = array("tax" => "none", "tax_sum" => 0);
        switch (number_format(floatval($tax_rate), 0, ".", "")) {
            case 0:
                if (!$zero_value_as_none) {
                    $taxes["tax"] = "vat0";
                }
                break;
            case 10:
                $taxes["tax"] = "vat10";
                break;
            case 18:
                $taxes["tax"] = "vat18";
                break;
            case 20:
                $taxes["tax"] = "vat20";
                break;
        }
        return $taxes;
    }

    public function checkDeliveryIncluded($delivery_price, $delivery_name)
    {
        $index = 0;
        foreach ($this->getFiscalCart() as $item) {
            if ($item["name"] == $delivery_name
                && $item["price"] == $delivery_price
                && $item["quantity"] == 1) {
                $this->delivery_index = $index;
                return true;

            }
            $index++;
        }
        return false;
    }

    public function getFiscalCartSum($delivery_included)
    {
        $fiscal_cart_sum = 0;
        $index = 0;
        foreach ($this->getFiscalCart() as $item) {
            if (!$delivery_included && $index == $this->delivery_index)
                continue;
            $fiscal_cart_sum += $item["sum"];
            $index++;
        }
        return $fiscal_cart_sum;
    }

    public function showDebugInfo($obj_to_debug)
    {
        echo "<pre>";
        var_dump($obj_to_debug);
        echo "</pre>";
    }

    public function correctPriceOfCartItem($corr_price_to_add, $item_position)
    {
        $item_sum = 0;
        $this->fiscal_cart[$item_position]["price"] += $corr_price_to_add;
        $item_sum = $this->fiscal_cart[$item_position]["price"] * $this->fiscal_cart[$item_position]["quantity"];
        $this->fiscal_cart[$item_position]["sum"] = $item_sum;
    }

    public function splitCartItem($cart_item_position)
    {
        $item_sum = 0;
        $item_price = 0;
        $item_quantity = 0;
        $item_price = $this->fiscal_cart[$cart_item_position]["price"];
        $item_quantity = $this->fiscal_cart[$cart_item_position]["quantity"] - 1;
        $this->fiscal_cart[$cart_item_position]["quantity"] = $item_quantity; //decreese quantity by one
        $this->fiscal_cart[$cart_item_position]["sum"] = $item_price * $item_quantity; //new sum
        //add one cart item to the end of cart
        $this->updateFiscalCart(
            $this->getPaymentFormType(),
            $this->fiscal_cart[$cart_item_position]["name"],
            $item_price, 1, $item_price,
            $this->fiscal_cart[$cart_item_position]["tax"]);
    }
}

