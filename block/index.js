/* global wp */
(function (wp) {
    'use strict';
    var el = wp.element.createElement, __ = wp.i18n.__, c = wp.components;
    var useState = wp.element.useState, useEffect = wp.element.useEffect, useRef = wp.element.useRef;
    var INHERIT = __('Default (from settings)', 'smart-cat-grid');
    var HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

    function inheritOptions(pairs) {
        return [{value: '', label: INHERIT}].concat(pairs.map(function (p) {
            return {value: p[0], label: p[1]};
        }));
    }

    function digits(value) {
        return String(value).replace(/[^0-9]/g, '');
    }

    function categoryLabel(r) {
        return r.name + ' (ID ' + r.id + ')';
    }

    function emptyPlaceholder() {
        return el(c.Placeholder, {
            icon: 'grid-view',
            label: __('Categories Grid', 'smart-cat-grid'),
            instructions: __('No categories found for the selected source.', 'smart-cat-grid')
        });
    }

    // Theme palette limited to hex colours: the server accepts hex values only.
    function useThemePalette() {
        var be = wp.blockEditor;
        var colors = be.useSettings ? be.useSettings('color.palette')[0] : be.useSetting('color.palette');
        return (colors || []).filter(function (item) { return item && HEX.test(item.color || ''); });
    }

    // Searches categories on the server, 20 at a time, instead of loading the whole taxonomy.
    function ParentPicker(props) {
        var input = useState(''), term = useState('');
        useEffect(function () {
            var timer = setTimeout(function () { term[1](input[0]); }, 300);
            return function () { clearTimeout(timer); };
        }, [input[0]]);
        var query = {per_page: 20, orderby: 'name', order: 'asc', hide_empty: false, _fields: 'id,name,parent'};
        if (term[0]) {
            query.search = term[0];
        }
        var found = wp.coreData.useEntityRecords('taxonomy', 'category', query);
        var selected = wp.coreData.useEntityRecord('taxonomy', 'category', props.value || 0, {enabled: !!props.value});
        var last = useRef([]);
        if (found.records) {
            last.current = found.records;
        }
        var options = last.current.map(function (r) { return {value: r.id, label: categoryLabel(r)}; });
        if (props.value && selected.record && !options.some(function (o) { return o.value === props.value; })) {
            options.unshift({value: props.value, label: categoryLabel(selected.record)});
        }
        return el(c.ComboboxControl, {
            label: __('Parent category', 'smart-cat-grid'),
            help: __('Type to search. Leave empty to use the default category from settings.', 'smart-cat-grid'),
            value: props.value || null,
            options: options,
            onFilterValueChange: function (v) { input[1](v || ''); },
            onChange: function (v) { props.onChange(parseInt(v, 10) || 0); }
        });
    }

    function Edit(props) {
        var a = props.attributes, set = props.setAttributes;
        var palette = useThemePalette();

        var panelSource = el(c.PanelBody, {title: __('Source', 'smart-cat-grid'), initialOpen: true},
            el(c.ToggleControl, {
                label: __('Auto-detect current category', 'smart-cat-grid'),
                help: __('Shows direct subcategories of the category being viewed. Preview is not available in the editor.', 'smart-cat-grid'),
                checked: !!a.auto,
                onChange: function (v) { set({auto: !!v}); }
            }),
            !a.auto && el(c.SelectControl, {
                label: __('Display', 'smart-cat-grid'),
                value: a.type,
                options: [
                    {value: 'subcategories', label: __('Subcategories of a category', 'smart-cat-grid')},
                    {value: 'top-level', label: __('Top-level categories', 'smart-cat-grid')}
                ],
                onChange: function (v) { set({type: v}); }
            }),
            !a.auto && a.type === 'subcategories' && el(ParentPicker, {
                value: a.categoryId,
                onChange: function (id) { set({categoryId: id}); }
            }),
            el(c.TextControl, {
                label: __('Exclude category IDs', 'smart-cat-grid'),
                help: __('Comma-separated, e.g. 10,20,30', 'smart-cat-grid'),
                value: a.exclude,
                onChange: function (v) { set({exclude: String(v).replace(/[^0-9, ]/g, '')}); }
            }),
            el(c.ToggleControl, {
                label: __('Hide empty categories', 'smart-cat-grid'),
                help: __('Hides categories without posts. A category stays visible when its subcategories have posts.', 'smart-cat-grid'),
                checked: !!a.hideEmpty,
                onChange: function (v) { set({hideEmpty: !!v}); }
            }),
            el(c.TextControl, {
                label: __('Limit', 'smart-cat-grid'),
                help: __('Leave empty to use the default. 0 = no limit.', 'smart-cat-grid'),
                type: 'number',
                min: 0,
                value: a.limit,
                onChange: function (v) { set({limit: digits(v)}); }
            }),
            el(c.ToggleControl, {
                label: __('Show "View All" button', 'smart-cat-grid'),
                help: __('The button appears only when the limit cuts the list. Turn off to hide it anyway.', 'smart-cat-grid'),
                checked: a.showViewAll !== false,
                onChange: function (v) { set({showViewAll: !!v}); }
            })
        );

        var panelDisplay = el(c.PanelBody, {title: __('Display', 'smart-cat-grid'), initialOpen: false},
            el(c.SelectControl, {
                label: __('Style', 'smart-cat-grid'),
                value: a.style,
                options: inheritOptions([
                    ['classic', __('Classic', 'smart-cat-grid')],
                    ['modern', __('Modern', 'smart-cat-grid')],
                    ['minimal', __('Minimal', 'smart-cat-grid')],
                    ['card', __('Card', 'smart-cat-grid')],
                    ['text', __('Text Only', 'smart-cat-grid')]
                ]),
                onChange: function (v) { set({style: v}); }
            }),
            el(c.SelectControl, {
                label: __('Columns', 'smart-cat-grid'),
                value: a.columns,
                options: inheritOptions(['2', '3', '4', '5', '6'].map(function (n) { return [n, n]; })),
                onChange: function (v) { set({columns: v}); }
            }),
            a.style !== 'text' && el(c.SelectControl, {
                label: __('Images', 'smart-cat-grid'),
                value: a.showImages,
                options: inheritOptions([['true', __('Show', 'smart-cat-grid')], ['false', __('Hide', 'smart-cat-grid')]]),
                onChange: function (v) { set({showImages: v}); }
            }),
            el(c.SelectControl, {
                label: __('Hover effect', 'smart-cat-grid'),
                value: a.hoverEffect,
                options: inheritOptions([['true', __('Enabled', 'smart-cat-grid')], ['false', __('Disabled', 'smart-cat-grid')]]),
                onChange: function (v) { set({hoverEffect: v}); }
            }),
            a.style !== 'text' && el(c.TextControl, {
                label: __('Image border radius (px)', 'smart-cat-grid'),
                type: 'number',
                min: 0,
                max: 50,
                value: a.imageRadius,
                onChange: function (v) { set({imageRadius: digits(v)}); }
            }),
            el(c.BaseControl, {
                label: __('Button color', 'smart-cat-grid'),
                help: __('Clear the color to use the default from settings.', 'smart-cat-grid')
            },
                el(c.ColorPalette, {
                    colors: palette,
                    value: a.buttonColor || undefined,
                    onChange: function (v) { set({buttonColor: v && HEX.test(v) ? v : ''}); }
                })
            )
        );

        var preview = a.auto
            ? el(c.Placeholder, {
                icon: 'grid-view',
                label: __('Categories Grid', 'smart-cat-grid'),
                instructions: __('Auto mode: subcategories of the current category will be rendered on the front end.', 'smart-cat-grid')
            })
            : el(wp.serverSideRender, {
                block: 'smart-cat-grid/categories-grid',
                attributes: a,
                EmptyResponsePlaceholder: emptyPlaceholder
            });

        return el(wp.element.Fragment, null,
            el(wp.blockEditor.InspectorControls, null, panelSource, panelDisplay),
            el('div', wp.blockEditor.useBlockProps(), preview));
    }

    wp.blocks.registerBlockType('smart-cat-grid/categories-grid', {edit: Edit, save: function () { return null; }});
})(window.wp);
