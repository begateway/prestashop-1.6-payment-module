{capture name=path}
    {l s='Confirm & Pay' mod='begateway'}
{/capture}

<h1 class="page-heading">
    {l s='Pay' mod='begateway'}
</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}
{if isset($nbProducts) && $nbProducts <= 0}
    <p class="warning">{l s='Shopping cart is empty.' mod='begateway'}</p>
{else}
  <img src="{$begateway_path|escape:'htmlall':'UTF-8'}views/img/creditcard.png" alt="{l s='Pay' mod='begateway'}"/>
  <form action="{$controller_link|escape:'htmlall':'UTF-8'}" method="post">
      <p>
          {l s='You have chosen to pay online.' mod='begateway'}
          {l s='The total amount of your order:' mod='begateway'}
          <strong>{displayPrice price=$total}</strong>
      </p>

      <p>
          <strong>{l s='To confirm and pay your order click the Checkout Button below' mod='begateway'}</strong>
      </p>

      <p class="cart_navigation" id="cart_navigation">
          <a href="{$link->getPageLink('order.php', true)|escape:'htmlall':'UTF-8'}?step=3"
             class="button button-exclusive btn btn-default"><i
                      class="icon-chevron-left"></i>{l s='Change method' mod='begateway'}</a>
          <input name="submitToken" type="hidden" value="{$submit_token|escape:'htmlall':'UTF-8'}"/>
          <button type="submit" class="button btn btn-default standard-checkout button-medium"><span>
                 {l s='Pay' mod='begateway'} <i class="icon-chevron-right right"></i> </span></button>
      </p>
  </form>
{/if}
