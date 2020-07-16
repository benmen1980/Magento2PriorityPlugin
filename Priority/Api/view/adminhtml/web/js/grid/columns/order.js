define([
    'Magento_Ui/js/grid/columns/column',
    'jquery',
    'mage/template',
    'text!Priority_Api/template/grid/cells/order.html',
    'Magento_Ui/js/modal/modal'
], function (Column, $, mageTemplate, sendmailPreviewTemplate) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'ui/grid/cells/html',
            fieldClass: {
                'data-grid-html-cell': true
            }
        },
        gethtml: function (row) {
            return row[this.index + '_html'];
        },
        getFormaction: function (row) {
            return row[this.index + '_formaction'];
        },
        getCustomerid: function (row) {
            return row[this.index + '_customerid'];
        },
        getRequestContent: function (row) {
            return row[this.index + '_requestcontent'];
        },
		getResponseContent: function (row) {
            return row[this.index + '_responsecontent'];
        },
        getLabel: function (row) {
            return row[this.index + '_html']
        },
        getTitle: function (row) {
            return row[this.index + '_title']
        },
        getSubmitlabel: function (row) {
            return row[this.index + '_submitlabel']
        },
        getCancellabel: function (row) {
            return row[this.index + '_cancellabel']
        },
        preview: function (row) {
            var modalHtml = mageTemplate(
                sendmailPreviewTemplate,
                {
                    html: this.gethtml(row),
                    title: this.getTitle(row),
                    label: this.getLabel(row),
                    formaction: this.getFormaction(row),
                    customerid: this.getCustomerid(row),
                    requestcontent: this.getRequestContent(row),
					responsecontent: this.getResponseContent(row),
                    submitlabel: this.getSubmitlabel(row),
                    cancellabel: this.getCancellabel(row),
                    linkText: $.mage.__('Go to Details Page')
                }
            );
            var previewPopup = $('<div/>').html(modalHtml);
            previewPopup.modal({
                title: this.getTitle(row),
                innerScroll: true,
                modalClass: '_image-box',
                }).trigger('openModal');
        },
        getFieldHandler: function (row) {
            return this.preview.bind(this, row);
        }
    });
});