{*
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
*}

{capture name=path}
    {l s='Order confirmed' mod='begateway'}
{/capture}

<h1 class="page-heading">
    {l s='Order confirmation' mod='begateway'}
</h1>

<div id="begateway__confirmation" class="begateway__confirmation bootstrap"
     data-update-url="{$status_link|escape:'htmlall':'UTF-8'}"
     data-order-id="{$order_id|intval}"
     data-success-msg="{l s='Your payment was completed! Please check order history for details.' mod='begateway'}"
     data-fail-msg="{l s='Your payment status was updated. Please check order history for details.' mod='begateway'}">
    <div id="begateway__loading" class="begateway__loading">
        <img class="begateway__loading-image" src="{$begateway_path|escape:'htmlall':'UTF-8'}views/img/img-loader.gif"
             alt="{l s='Please wait' mod='begateway'}"/>

        <p>{l s='Waiting for payment status update...' mod='begateway'}</p>
    </div>

    <div id="begateway__message" class="alert">

    </div>

    <div>
        <p class="center">
            <a class="exclusive_large" href="{$link->getPageLink('history.php', true)|escape:'htmlall':'UTF-8'}">
                {l s='Show orders history' mod='begateway'}</a>
        </p>
    </div>
</div>
