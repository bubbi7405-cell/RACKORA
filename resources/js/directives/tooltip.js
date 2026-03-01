export default {
    mounted(el, binding) {
        el.addEventListener('mouseenter', (event) => {
            const content = binding.value;
            if (!content) return;

            let text = '';
            let title = '';

            if (typeof content === 'string') {
                text = content;
            } else if (typeof content === 'object') {
                text = content.text;
                title = content.title || '';
            }

            window.dispatchEvent(new CustomEvent('show-tooltip', {
                detail: { event, content: text, title }
            }));
        });

        el.addEventListener('mouseleave', () => {
            window.dispatchEvent(new CustomEvent('hide-tooltip'));
        });

        el.addEventListener('mousedown', () => {
            window.dispatchEvent(new CustomEvent('hide-tooltip'));
        });
    }
};
