{*
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
*}
<br/>
<div style="float: left">
    <fieldset style="width: 400px;">
        <legend>{l s='Online Void' mod='begateway'}</legend>
        {foreach from=$errors item=error}
            <p class="alert alert-danger">{$error|escape:'htmlall':'UTF-8'}</p>
        {/foreach}
        <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="id_order" value="{$params.id_order|intval}"/>

            <p class="center">
                <button type="submit" class="btn btn-default" name="submitBeGatewayOnlineVoid"
                        onclick="if (!confirm('{l s='Are you sure?' mod='begateway'}'))return false;">
                    <i class="icon-undo"></i>
                    {l s='Void online' mod='begateway'}
                </button>
            </p>
        </form>
    </fieldset>
</div>
