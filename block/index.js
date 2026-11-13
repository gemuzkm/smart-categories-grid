/* global wp */
(function (wp) {
    'use strict';
    var el = wp.element.createElement, __ = wp.i18n.__, c = wp.components;
    var inherit = __('Default (from settings)', 'smart-cat-grid');
    function Edit(props) {
        var a = props.attributes, set = props.setAttributes;
        var records = wp.coreData.useEntityRecords('taxonomy', 'category', {per_page: -1, hide_empty: false, _fields: 'id,name,parent'});
        function change(key, value) { var next = {}; next[key] = value; set(next); }
        function select(key, label, options) {
            return el(c.SelectControl, {label: label, value: a[key], options: options, onChange: function(v) {change(key, v);}});
        }
        function text(key, label, numeric) {
            return el(c.TextControl, {label: label, type: numeric ? 'number' : 'text', min: numeric ? 0 : undefined, value: a[key], onChange: function(v) {change(key, numeric ? String(v).replace(/[^0-9]/g, '') : v);}});
        }
        function toggle(key, label) {
            return el(c.ToggleControl, {label:label, checked:!!a[key], onChange:function(v) {change(key, !!v);}});
        }
        var parentOptions = [{value:0,label:inherit}];
        var all = records.records || [], byId = {};
        all.forEach(function(r) {byId[r.id] = r;});
        function path(r) {
            var names = [r.name], seen = {}, p = r.parent;
            while (byId[p] && !seen[p]) {seen[p] = true; names.unshift(byId[p].name); p = byId[p].parent;}
            return names;
        }
        all.slice().sort(function(a,b) {return path(a).join(' / ').localeCompare(path(b).join(' / '));}).forEach(function(r) {
            parentOptions.push({value:r.id,label:new Array(path(r).length).join('— ') + r.name});
        });
        var boolOptions = [{value:'',label:inherit},{value:'true',label:__('Enabled', 'smart-cat-grid')},{value:'false',label:__('Disabled', 'smart-cat-grid')}];
        var styles = [{value:'',label:inherit}].concat([
            ['classic',__('Classic', 'smart-cat-grid')], ['modern',__('Modern', 'smart-cat-grid')],
            ['minimal',__('Minimal', 'smart-cat-grid')], ['card',__('Card', 'smart-cat-grid')], ['text',__('Text Only', 'smart-cat-grid')]
        ].map(function(v) {return {value:v[0],label:v[1]};}));
        var preview = a.auto ? el(c.Placeholder, {icon:'grid-view',label:__('Categories Grid', 'smart-cat-grid'),instructions:__('Auto mode: subcategories of the current category will be rendered on the front end.', 'smart-cat-grid')})
            : el(wp.serverSideRender, {block:'smart-cat-grid/categories-grid',attributes:a});
        return el(wp.element.Fragment, null,
            el(wp.blockEditor.InspectorControls, null,
                el(c.PanelBody, {title:__('Source', 'smart-cat-grid'),initialOpen:true},
                    toggle('auto',__('Auto-detect current category', 'smart-cat-grid')),
                    !a.auto && select('type',__('Display', 'smart-cat-grid'),[{value:'subcategories',label:__('Subcategories', 'smart-cat-grid')},{value:'top-level',label:__('Top-level categories', 'smart-cat-grid')}]),
                    !a.auto && a.type === 'subcategories' && el(c.SelectControl, {label:__('Parent category', 'smart-cat-grid'),value:a.categoryId,options:parentOptions,onChange:function(v) {change('categoryId',parseInt(v,10)||0);}}),
                    text('exclude',__('Exclude category IDs', 'smart-cat-grid'),false),
                    toggle('hideEmpty',__('Hide empty categories', 'smart-cat-grid')),
                    text('limit',__('Limit (0 = unlimited; blank = default)', 'smart-cat-grid'),true),
                    toggle('showViewAll',__('Show "View All" button', 'smart-cat-grid'))),
                el(c.PanelBody, {title:__('Display', 'smart-cat-grid'),initialOpen:false},
                    select('style',__('Style', 'smart-cat-grid'),styles),
                    select('columns',__('Columns', 'smart-cat-grid'),[{value:'',label:inherit}].concat(['2','3','4','5','6'].map(function(v){return {value:v,label:v};}))),
                    select('showImages',__('Images', 'smart-cat-grid'),boolOptions),
                    select('hoverEffect',__('Hover effect', 'smart-cat-grid'),boolOptions),
                    text('imageRadius',__('Image radius (0-50 px)', 'smart-cat-grid'),true),
                    text('buttonColor',__('Button color (hex)', 'smart-cat-grid'),false))),
            el('div',wp.blockEditor.useBlockProps(),preview));
    }
    wp.blocks.registerBlockType('smart-cat-grid/categories-grid',{edit:Edit,save:function(){return null;}});
})(window.wp);
