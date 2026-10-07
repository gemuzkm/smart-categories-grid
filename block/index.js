/* global wp */
( function ( wp ) {
	'use strict';

	var el               = wp.element.createElement;
	var Fragment         = wp.element.Fragment;
	var __               = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps    = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var useEntityRecords = wp.coreData.useEntityRecords;
	var c                = wp.components;

	var INHERIT = __( 'Default (from settings)', 'smart-cat-grid' );

	function categoryOptions( records ) {
		var options = [ { value: 0, label: INHERIT } ];
		if ( ! records ) {
			return options;
		}
		var byParent = {};
		records.forEach( function ( r ) {
			( byParent[ r.parent ] = byParent[ r.parent ] || [] ).push( r );
		} );
		function walk( parent, depth ) {
			( byParent[ parent ] || [] )
				.sort( function ( a, b ) { return a.name.localeCompare( b.name ); } )
				.forEach( function ( r ) {
					options.push( { value: r.id, label: new Array( depth + 1 ).join( '— ' ) + r.name } );
					walk( r.id, depth + 1 );
				} );
		}
		walk( 0, 0 );
		return options;
	}

	function Edit( props ) {
		var a   = props.attributes;
		var set = props.setAttributes;

		var cats = useEntityRecords( 'taxonomy', 'category', { per_page: -1, hide_empty: false, _fields: 'id,name,parent' } );

		var panelSource = el( c.PanelBody, { title: __( 'Source', 'smart-cat-grid' ), initialOpen: true },
			el( c.ToggleControl, {
				label: __( 'Auto-detect current category', 'smart-cat-grid' ),
				help: __( 'Shows direct subcategories of the category being viewed. Preview is not available in the editor.', 'smart-cat-grid' ),
				checked: !! a.auto,
				onChange: function ( v ) { set( { auto: !! v } ); }
			} ),
			! a.auto && el( c.SelectControl, {
				label: __( 'Display', 'smart-cat-grid' ),
				value: a.type,
				options: [
					{ value: 'subcategories', label: __( 'Subcategories of a category', 'smart-cat-grid' ) },
					{ value: 'top-level',     label: __( 'Top-level categories', 'smart-cat-grid' ) }
				],
				onChange: function ( v ) { set( { type: v } ); }
			} ),
			! a.auto && a.type === 'subcategories' && el( c.SelectControl, {
				label: __( 'Parent category', 'smart-cat-grid' ),
				value: a.categoryId,
				options: categoryOptions( cats.records ),
				onChange: function ( v ) { set( { categoryId: parseInt( v, 10 ) || 0 } ); }
			} ),
			el( c.TextControl, {
				label: __( 'Exclude category IDs', 'smart-cat-grid' ),
				help: __( 'Comma-separated, e.g. 10,20,30', 'smart-cat-grid' ),
				value: a.exclude,
				onChange: function ( v ) { set( { exclude: v.replace( /[^0-9,\s]/g, '' ) } ); }
			} ),
			el( c.TextControl, {
				label: __( 'Limit', 'smart-cat-grid' ),
				help: __( 'Leave empty to use the default. 0 = no limit.', 'smart-cat-grid' ),
				type: 'number',
				min: 0,
				value: a.limit,
				onChange: function ( v ) { set( { limit: String( v ).replace( /[^0-9]/g, '' ) } ); }
			} )
		);

		var panelDisplay = el( c.PanelBody, { title: __( 'Display', 'smart-cat-grid' ), initialOpen: false },
			el( c.SelectControl, {
				label: __( 'Style', 'smart-cat-grid' ),
				value: a.style,
				options: [
					{ value: '',        label: INHERIT },
					{ value: 'classic', label: __( 'Classic', 'smart-cat-grid' ) },
					{ value: 'modern',  label: __( 'Modern', 'smart-cat-grid' ) },
					{ value: 'minimal', label: __( 'Minimal', 'smart-cat-grid' ) },
					{ value: 'card',    label: __( 'Card', 'smart-cat-grid' ) },
					{ value: 'text',    label: __( 'Text Only', 'smart-cat-grid' ) }
				],
				onChange: function ( v ) { set( { style: v } ); }
			} ),
			el( c.SelectControl, {
				label: __( 'Columns', 'smart-cat-grid' ),
				value: a.columns,
				options: [ { value: '', label: INHERIT } ].concat( [ '2', '3', '4', '5', '6' ].map( function ( n ) {
					return { value: n, label: n };
				} ) ),
				onChange: function ( v ) { set( { columns: v } ); }
			} ),
			a.style !== 'text' && el( c.SelectControl, {
				label: __( 'Images', 'smart-cat-grid' ),
				value: a.showImages,
				options: [
					{ value: '',      label: INHERIT },
					{ value: 'true',  label: __( 'Show', 'smart-cat-grid' ) },
					{ value: 'false', label: __( 'Hide', 'smart-cat-grid' ) }
				],
				onChange: function ( v ) { set( { showImages: v } ); }
			} ),
			el( c.SelectControl, {
				label: __( 'Hover effect', 'smart-cat-grid' ),
				value: a.hoverEffect,
				options: [
					{ value: '',      label: INHERIT },
					{ value: 'true',  label: __( 'Enabled', 'smart-cat-grid' ) },
					{ value: 'false', label: __( 'Disabled', 'smart-cat-grid' ) }
				],
				onChange: function ( v ) { set( { hoverEffect: v } ); }
			} ),
			a.style !== 'text' && el( c.TextControl, {
				label: __( 'Image border radius (px)', 'smart-cat-grid' ),
				type: 'number',
				min: 0,
				max: 50,
				value: a.imageRadius,
				onChange: function ( v ) { set( { imageRadius: String( v ).replace( /[^0-9]/g, '' ) } ); }
			} ),
			el( c.TextControl, {
				label: __( 'Button color (hex)', 'smart-cat-grid' ),
				placeholder: '#b93434',
				value: a.buttonColor,
				onChange: function ( v ) { set( { buttonColor: v.trim() } ); }
			} )
		);

		var preview;
		if ( a.auto ) {
			preview = el( c.Placeholder, {
				icon: 'grid-view',
				label: __( 'Categories Grid', 'smart-cat-grid' ),
				instructions: __( 'Auto mode: subcategories of the current category will be rendered on the front end.', 'smart-cat-grid' )
			} );
		} else {
			preview = el( ServerSideRender, {
				block: 'smart-cat-grid/categories-grid',
				attributes: a,
				EmptyResponsePlaceholder: function () {
					return el( c.Placeholder, {
						icon: 'grid-view',
						label: __( 'Categories Grid', 'smart-cat-grid' ),
						instructions: __( 'No categories found for the selected source.', 'smart-cat-grid' )
					} );
				}
			} );
		}

		return el( Fragment, null,
			el( InspectorControls, null, panelSource, panelDisplay ),
			el( 'div', useBlockProps(), preview )
		);
	}

	registerBlockType( 'smart-cat-grid/categories-grid', {
		edit: Edit,
		save: function () { return null; }
	} );
} )( window.wp );
