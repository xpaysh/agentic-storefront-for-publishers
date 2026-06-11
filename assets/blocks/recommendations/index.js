/* global wp */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'xpay/recommendations', {
		edit: function ( props ) {
			var attrs = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = blockEditor.useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{ title: __( 'Recommendations', 'xpay-agentic-commerce-for-publishers' ), initialOpen: true },
						el( components.TextControl, {
							label: __( 'Heading (optional)', 'xpay-agentic-commerce-for-publishers' ),
							value: attrs.title || '',
							onChange: function ( v ) {
								setAttributes( { title: v } );
							}
						} ),
						el( components.RangeControl, {
							label: __( 'Maximum products', 'xpay-agentic-commerce-for-publishers' ),
							value: attrs.limit || 3,
							min: 1,
							max: 12,
							onChange: function ( v ) {
								setAttributes( { limit: v } );
							}
						} ),
						el( components.SelectControl, {
							label: __( 'Layout', 'xpay-agentic-commerce-for-publishers' ),
							value: attrs.layout || 'cards',
							options: [
								{ label: __( 'Cards', 'xpay-agentic-commerce-for-publishers' ), value: 'cards' },
								{ label: __( 'Compact list', 'xpay-agentic-commerce-for-publishers' ), value: 'list' }
							],
							onChange: function ( v ) {
								setAttributes( { layout: v } );
							}
						} )
					)
				),
				el(
					'div',
					{ style: { padding: '12px', border: '1px dashed #c3c4c7', borderRadius: '6px', background: '#fff' } },
					el( 'strong', {}, __( 'Recommendations', 'xpay-agentic-commerce-for-publishers' ) ),
					attrs.title ? el( 'div', {}, '"' + attrs.title + '"' ) : null,
					el( 'div', { style: { color: '#6b7280', fontSize: '12px', marginTop: '4px' } },
						__( 'Live recommendations will render on the published page.', 'xpay-agentic-commerce-for-publishers' )
					)
				)
			);
		},
		save: function () {
			// Server-rendered.
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
