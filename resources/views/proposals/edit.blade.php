@extends('header')

@section('head')
    @parent

    @include('money_script')
    @include('proposals.grapesjs_header')

@stop

@section('content')

    {!! Former::open($url)
            ->method($method)
            ->onsubmit('return onFormSubmit(event)')
            ->id('mainForm')
            ->autocomplete('off')
            ->addClass('warn-on-exit')
            ->rules([
                'invoice_id' => 'required',
            ]) !!}

    @if ($proposal)
        {!! Former::populate($proposal) !!}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('action') !!}
        {!! Former::text('html') !!}
        {!! Former::text('css') !!}
    </span>

    <div class="row">
		<div class="col-lg-12">
            <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        {!! Former::select('invoice_id')->addOption('', '')
                                ->label(trans('texts.quote'))
                                ->addGroupClass('invoice-select') !!}
                        {!! Former::select('proposal_template_id')->addOption('', '')
                                ->label(trans('texts.template'))
                                ->addGroupClass('template-select') !!}

                    </div>
                    <div class="col-md-6">
                        {!! Former::textarea('private_notes')
                                ->style('height: 100px') !!}
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    @if(Auth::user()->canCreateOrEdit(ENTITY_PROPOSAL, $proposal))
    <center class="buttons">
        {!! Button::normal(trans('texts.cancel'))
                ->appendIcon(Icon::create('remove-circle'))
                ->asLinkTo(HTMLUtils::previousUrl('/proposals')) !!}

        @if ($proposal)
            {!! Button::primary(trans('texts.download'))
                    ->withAttributes(['onclick' => 'onDownloadClick()'])
                    ->appendIcon(Icon::create('download-alt')) !!}
        @endif

        {!! Button::success(trans("texts.save"))
                ->withAttributes(['id' => 'saveButton'])
                ->submit()
                ->appendIcon(Icon::create('floppy-disk')) !!}

        {!! Button::info(trans('texts.email'))
                ->withAttributes(['id' => 'emailButton', 'onclick' => 'onEmailClick()'])
                ->appendIcon(Icon::create('send')) !!}

        @if ($proposal)
            {!! DropdownButton::normal(trans('texts.more_actions'))
                    ->withContents($proposal->present()->moreActions()) !!}
        @endif

    </center>
    @endif

    {!! Former::close() !!}

    <div id="gjs"></div>

    <script type="text/javascript">
    var invoices = {!! $invoices !!};
    var invoiceMap = {};

    var templates = {!! $templates !!};
    var templateMap = {};
    var isFormSubmitting = false;

    function onFormSubmit() {
        // prevent duplicate form submissions
        if (isFormSubmitting) {
            return;
        }
        isFormSubmitting = true;
        $('#saveButton, #emailButton').prop('disabled', true);

        $('#html').val(grapesjsEditor.getHtml());
        $('#css').val(grapesjsEditor.getCss());

        return true;
    }

    function onEmailClick() {
        sweetConfirm(function() {
            $('#action').val('email');
            $('#saveButton').click();
        })
    }

    @if ($proposal)
        function onDownloadClick() {
            location.href = "{{ url("/proposals/{$proposal->public_id}/download") }}";
        }
    @endif

    function loadTemplate() {
        var templateId = $('select#proposal_template_id').val();
        var template = templateMap[templateId];

        if (! template) {
            return;
        }

        var html = mergeTemplate(template.html);
        grapesjsEditor.CssComposer.getAll().reset();
        grapesjsEditor.setComponents(html);
        // Addition (Dylan) : Set custom css
        template.css = customCss(template.css);
        grapesjsEditor.setStyle(template.css);
    }

    /**
     * Addition (Dylan) : Custom css for proposal templates
     *
     * @param css
     * @returns {*}
     */
    function customCss(css) {
        css = css.replace(
            "body{font-family:\"Open Sans\", Helvetica, arial, sans-serif;",
            "body{font-family: century-gothic, sans-serif;font-weight: 400;font-style: normal;"
        );
        css = css.replace(
            ".proposal-info td{padding-top:0px;padding-right:0px;padding-bottom:120px;padding-left:0px;}",
            ".proposal-info td{padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;}"
        );
        css += ".rg-font{font-family: century-gothic, sans-serif;font-weight: 400;font-style: normal;}";
        css += ".w-100{width:100%;}";
        css += ".w-19{width:19%;}";
        css += ".w-12{width:12%;}";
        css += ".w-35{width:35%;}";
        css += ".w-17{width:17%;}";
        css += ".w-16{width:16%;}";
        css += ".text-right{text-align:right;}";
        css += ".rg-color{color:rgb(19, 95, 108);}";
        css += ".padding-right-10{padding-right: 10px;}";
        css += ".padding-left-10{padding-left: 10px;}";
        css += ".padding-top-10{padding-top: 10px;}";
        css += ".padding-right-5{padding-right: 5px;}";
        css += ".padding-left-5{padding-left: 5px;}";
        css += ".padding-top-5{padding-top: 5px;}";
        css += ".padding-bottom-5{padding-bottom: 5px;}";
        css += ".padding-bottom-5{padding-bottom: 5px;}";
        css += ".quote-pricing {border-spacing:0;border: solid #000000; border-width: 0px 0px 1px 0px;}";
        css += ".quote-pricing-header {background-color: #1c1c1c; color: #FFFFFF;}";
        css += ".quote-pricing-footer {background-color: #1c1c1c; color: #FFFFFF;}";
        css += ".font-size-14 {font-size: 14px;}";
        return css;
    }

    function mergeTemplate(html) {
        var invoiceId = $('select#invoice_id').val();
        var invoice = invoiceMap[invoiceId];

        if (!invoice) {
            return html;
        }

        invoice.account = {!! auth()->user()->account->load('country') !!};
        invoice.contact = invoice.client.contacts[0];

        var regExp = new RegExp(/\$[a-z][\w\.]*/g);
        var matches = html.match(regExp);

        if (matches) {
            for (var i=0; i<matches.length; i++) {
                var match = matches[i];

                field = match.replace('$quote.', '$');
                field = field.substring(1, field.length);
                field = toSnakeCase(field);

                if (field == 'quote_number') {
                    field = 'invoice_number';
                } else if (field == 'valid_until') {
                    field = 'due_date';
                } else if (field == 'quote_date') {
                    field = 'invoice_date';
                } else if (field == 'footer') {
                    field = 'invoice_footer';
                } else if (match == '$account.phone') {
                    field = 'account.work_phone';
                } else if (match == '$client.phone') {
                    field = 'client.phone';
                }

                if (field == 'logo_url') {
                    var value = "{{ $account->getLogoURL() }}";
                } else if (field == 'quote_image_url') {
                    var value = "{{ asset('/images/quote.png') }}";
                } else if (match == '$client.name') {
                    var value = getClientDisplayName(invoice.client);
                }
                // Addition (Dylan) : Return formatted table of invoice items for propositions
                else if (match == '$quote.invoice_items') {
                    var value = getInvoiceItemsTable(invoice);
                }
                // Addition (Dylan) : Recognize today date short code
                else if (match == '$todayDate') {
                    var today = new Date();
                    value = ('0' + today.getDate()).slice(-2) + '-' + ('0' + (today.getMonth()+1)).slice(-2) + '-' + today.getFullYear();
                }
                else {
                    var value = getDescendantProp(invoice, field) || ' ';
                }

                value = doubleDollarSign(value) + '';
                value = value.replace(/\n/g, "\\n").replace(/\r/g, "\\r");

                if (['amount', 'partial', 'client.balance', 'client.paid_to_date'].indexOf(field) >= 0) {
                        value = formatMoneyInvoice(value, invoice);
                } else if (['invoice_date', 'due_date', 'partial_due_date'].indexOf(field) >= 0) {
                    value = moment.utc(value).format('{{ $account->getMomentDateFormat() }}');
                }

                html = html.replace(match, value);
            }
        }

        return html;
    }

    @if ($proposal)
        function onArchiveClick() {
            submitForm_proposal('archive', {{ $proposal->id }});
    	}

        function onDeleteClick() {
            submitForm_proposal('delete', {{ $proposal->id }});
        }
    @endif

    $(function() {
        var invoiceId = {{ ! empty($invoicePublicId) ? $invoicePublicId : 0 }};
        var $invoiceSelect = $('select#invoice_id');
        for (var i = 0; i < invoices.length; i++) {
            var invoice = invoices[i];
            invoiceMap[invoice.public_id] = invoice;
            $invoiceSelect.append(new Option(invoice.invoice_number + ' - ' + getClientDisplayName(invoice.client), invoice.public_id));
        }
        @include('partials/entity_combobox', ['entityType' => ENTITY_INVOICE])
        if (invoiceId) {
            var invoice = invoiceMap[invoiceId];
            if (invoice) {
                $invoiceSelect.val(invoice.public_id);
                setComboboxValue($('.invoice-select'), invoice.public_id, invoice.invoice_number + ' - ' + getClientDisplayName(invoice.client));
            }
        }
        $invoiceSelect.change(loadTemplate);

        var templateId = {{ ! empty($templatePublicId) ? $templatePublicId : 0 }};
        var $proposal_templateSelect = $('select#proposal_template_id');
        for (var i = 0; i < templates.length; i++) {
            var template = templates[i];
            templateMap[template.public_id] = template;
            $proposal_templateSelect.append(new Option(template.name, template.public_id));
        }
        @include('partials/entity_combobox', ['entityType' => ENTITY_PROPOSAL_TEMPLATE])
        if (templateId) {
            var template = templateMap[templateId];
            $proposal_templateSelect.val(template.public_id);
            setComboboxValue($('.template-select'), template.public_id, template.name);
        }
        $proposal_templateSelect.change(loadTemplate);
	})

    </script>

    @include('partials.bulk_form', ['entityType' => ENTITY_PROPOSAL])
    @include('proposals.grapesjs', ['entity' => $proposal])

    <script type="text/javascript">

    $(function() {
        grapesjsEditor.on('canvas:drop', function(event, block) {
            if (! block.attributes || block.attributes.type != 'image') {
                var html = mergeTemplate(grapesjsEditor.getHtml());
                grapesjsEditor.setComponents(html);
            }
        });

        @if (! $proposal && $templatePublicId)
            loadTemplate();
        @endif

        @if (request()->show_assets)
            setTimeout(function() {
                grapesjsEditor.runCommand('open-assets');
            }, 500);
        @endif
    });

    </script>

@stop
