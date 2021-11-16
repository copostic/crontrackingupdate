/**
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/
$(function () {

    var $container = $('div#crontrackingupdate-category-container');
    var $buttonSelect = $container.find('button.btn-select');
    var $listContainer = $container.find('div.crontrackingupdate-categories-list-container');
    var $ulCategories = $container.find('ul#crontrackingupdate-categories-list');
    $('select.crontrackingupdate-category-select-source option').each(function () {
        var img = $(this).data('thumbnail');
        var link = $(this).data('link');
        var text = this.innerText;
        var value = $(this).val();
        var item = '<li><a href="' + link + '"><img src="' + img + '" alt="" value="' + value + '"/><span>' + text + '</span></li>';
        $ulCategories.append(item);
    });

    //change button stuff on click
    $ulCategories.on('click', 'li', function () {
        var img = $(this).find('img').attr('src');
        var value = $(this).find('img').attr('value');
        var text = this.innerText;
        var item = '<img src="' + img + '" alt="" /><span>' + text + '</span>';
        $buttonSelect.html(item).attr('value', value);
        $("div.crontrackingupdate-categories-list-container").hide();
    }).find('li:first-child').trigger('click');

    $(".btn-select").click(function () {
        $listContainer.toggle();
    });

}, 1000);
