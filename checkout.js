const settings = window.wc.wcSettings.getSetting( 'jifitipayment_data', {} );
const label = window.wp.htmlEntities.decodeEntities( ' ' + settings.title + ' ' ) || window.wp.i18n.__( 'Buy Now, Pay later', 'jifitipayment' );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};

const Label = () => {
	return window.React.createElement(
        'span',
        {},
        [
            label, 
            settings.icon ? window.React.createElement(
                'img',
                {src: settings.icon, style:{ height: 'auto', maxWidth: '100px', maxHeight: '30px', verticalAlign: 'middle' }},
                null
            ): ''
        ]
    );
}


const Block_Gateway = {
    name: 'jifitipayment',
    label: Object( window.wp.element.createElement )(Label , null ) ,
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );