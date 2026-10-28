=== Prism Works Site Builder ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0

Builds the Prism Works Studio website as editable Elementor pages: Home, About us, Services (with pricing),
Sectors, Contact and a branded "Page not found" page. Content follows prismworksstudio.co.uk; the look follows
prismworks.studio. Header/footer, popup, forms and the 404 page use ready-made plugins; the live chat and AI
assistant are built into this plugin.

== Install ==
1. Plugins > Add New > Upload Plugin > prism-works-site-builder.zip > Install > Activate.
2. A popup lists the plugins it needs. Click "Install & activate" next to each (the site needs internet access):
   - Elementor ............ page builder
   - Ultimate Addons for Elementor (UAE) ... header and footer on every page
   - Contact Form 7 ....... every form on the site
   - Popup Maker .......... the free-consultation popup
   - 404page .............. shows the "Page not found" page
3. When all are active, click "Activate Prism Works Site Builder" in the popup. Only then are the pages, header,
   footer, forms, popups, chat and site icon created. Home becomes the front page.

== Live chat and AI assistant (built in) ==
- A chat button appears on every page. It answers questions about services, pricing and timelines straight away
  from the site's own content, and offers "Book a free call" (opens the popup) and "Talk to a human".
- "Talk to a human" shows a Contact Form 7 form ("Prism Works Chat") inside the chat window. The message and the
  chat so far are emailed to your team and saved under Prism Works > Enquiries.
- AI mode (optional): Prism Works > Chat settings > paste a Claude API key. The assistant then answers in natural
  language using the same site facts. The key stays on the server.
- Real-time replies from a person (optional): paste a Tawk.to / Crisp embed code under Prism Works > Chat settings.

== After setup ==
- Popup: opens by itself a few seconds after the home page loads (once per visit) and from every button that
  points to the Contact page. Edit it under Popup Maker > All Popups.
- Forms: Contact Form 7 > Contact Forms. Emails go to the site admin address unless you set one under Prism Works.
- Pages: Prism Works (left menu) > Edit with Elementor. Header and footer: Appearance > Ultimate Addons.
- "Reset all pages to original design" restores every page, the header, the footer, all forms and popups.

== Uploading a newer zip ==
- Upload the new zip over the old one (Plugins > Add New > Upload Plugin > "Replace current with uploaded").
- Your pages, header, footer, forms and popups are NEVER changed by an upload, so nothing anyone else edited is lost.
  Design files (CSS/JS) update by themselves.
- If the new zip has different page content, a notice appears in wp-admin saying nothing has been changed. Press
  "Reset all pages to original design" on the Prism Works screen only when you want the new content. Your current
  pages are backed up first, and "Undo last reset" puts them back. Or choose "Keep my pages and hide this".
- If an upload is incomplete or the server is too old, the plugin pauses itself with a notice and your site is
  left exactly as it was.
