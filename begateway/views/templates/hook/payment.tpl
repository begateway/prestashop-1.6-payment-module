{*
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
*}

<div class="row">
    <div class="col-xs-12 col-md-6">
        <div class="payment_module" id="begateway_payment_module">
            <a href="{$contoller_link|escape:'htmlall':'UTF-8'}" title="{l s='Proceed to payment' mod='begateway'}" class="begateway">
                <p>{l s='Proceed to payment' mod='begateway'}</p>
                <p>
                  {foreach from=$images item=image}
                    <img src="{$begateway_path|escape:'htmlall':'UTF-8'}views/img/{$image|lower}.png" />
                  {/foreach}
                </p>
                <br class="clearfix" />
            </a>
        </div>
    </div>
</div>
