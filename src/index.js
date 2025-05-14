import { decodeEntities } from '@wordpress/html-entities';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('wc_gateway_precisionpay_data', {});

const label = decodeEntities(settings.title);

const Content = () => {
	return decodeEntities(settings.description || '');
};

const Label = (props) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={label} />;
};

const PrecisionPay = {
	name: "wc_gateway_precisionpay",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	// paymentMethodId: 'wc_gateway_precisionpay',
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod(PrecisionPay);