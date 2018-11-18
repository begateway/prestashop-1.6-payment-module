{*
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
*}
<div class="row">
    <div class="col-lg-12">
        <div class="panel">
            <div class="panel-heading">{l s='Online Refund' mod='begateway'}</div>
            {foreach from=$errors item=error}
                <p class="alert alert-danger">{$error|escape:'htmlall':'UTF-8'}</p>
            {/foreach}
            <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}">
                <input type="hidden" name="id_order" value="{$params.id_order|intval}"/>

                <p>{l s='Send amount to refund' mod='begateway'}</p>

                <p class="center">
                    <input value="{$max_online_refund_amount|escape:'htmlall':'UTF-8'}" name="BEGATEWA_REFUND_AMOUNT" />
                    <button type="submit" class="btn btn-default" name="submitBeGatewayOnlineRefund"
                            onclick="if (!confirm('{l s='Are you sure?' mod='begateway'}'))return false;">
                        <i class="icon-undo"></i>
                        {l s='Refund online' mod='begateway'}
                    </button>
                </p>
            </form>
        </div>
    </div>
</div>
