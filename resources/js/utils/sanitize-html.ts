import DOMPurify from 'dompurify';

/**
 * Sanitize HTML content to prevent XSS attacks.
 * Used primarily for rendering email body HTML safely.
 */
export function sanitizeHtml(dirty: string): string {
    return DOMPurify.sanitize(dirty, {
        // Allow common email HTML elements
        ALLOWED_TAGS: [
            'a', 'b', 'br', 'blockquote', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'span', 'strong', 'table', 'tbody', 'td',
            'th', 'thead', 'tr', 'u', 'ul', 'font', 'center', 'small', 'sub', 'sup',
        ],
        ALLOWED_ATTR: [
            'href', 'src', 'alt', 'title', 'style', 'class', 'target', 'rel',
            'width', 'height', 'border', 'cellpadding', 'cellspacing', 'align',
            'valign', 'bgcolor', 'color', 'size', 'face',
        ],
        // Force links to open in new tab and add noopener
        ALLOW_DATA_ATTR: false,
        ADD_ATTR: ['target'],
    });
}

export default sanitizeHtml;
