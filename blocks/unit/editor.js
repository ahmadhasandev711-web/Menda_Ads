/* global wp, mendaUnitBlock */
( function ( wp ) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var __ = wp.i18n.__;
	var choices = ( window.mendaUnitBlock && window.mendaUnitBlock.placements ) ? window.mendaUnitBlock.placements : [];

	function edit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps( {
			className: 'menda-unit-editor',
			style: {
				padding: '16px',
				border: '1px dashed #c3c4c7',
				background: '#fff',
			},
		} );
		var selected = attributes.placement
			? ( attributes.placement + ( attributes.adId ? ' / #' + attributes.adId : '' ) )
			: ( attributes.adId ? '#' + attributes.adId : __( 'Choose a placement in the sidebar.', 'menda-ads' ) );

		return el(
			'div',
			blockProps,
			el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Unit settings', 'menda-ads' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Placement', 'menda-ads' ),
						value: attributes.placement || '',
						options: choices,
						onChange: function ( value ) {
							setAttributes( { placement: value } );
						},
					} ),
					el( TextControl, {
						label: __( 'Or specific unit ID', 'menda-ads' ),
						type: 'number',
						value: attributes.adId || '',
						onChange: function ( value ) {
							setAttributes( { adId: parseInt( value, 10 ) || 0 } );
						},
					} ),
					el( RangeControl, {
						label: __( 'Count', 'menda-ads' ),
						value: attributes.count,
						min: 1,
						max: 10,
						onChange: function ( value ) {
							setAttributes( { count: value } );
						},
					} )
				)
			),
			el( 'p', { style: { margin: 0, fontWeight: 600 } }, __( 'Menda unit', 'menda-ads' ) ),
			el( 'p', { style: { margin: '8px 0 0', color: '#646970', fontSize: '12px' } }, selected )
		);
	}

	registerBlockType( 'menda/unit', {
		edit: edit,
		save: function () {
			return null;
		},
	} );
} )( window.wp );
