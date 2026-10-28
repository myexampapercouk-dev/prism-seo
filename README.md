# Prism Works Studio: site builder plugin + SEO

The WordPress plugin `prism-works-site-builder/` builds the whole prismworksstudio.co.uk site: Elementor pages, header, footer, forms, popups and chat. SEO work is done here, in the plugin, then shipped as a new zip.

## Structure

```
prism-works-site-builder/        the plugin (zip this folder to upload)
  prism-works-site-builder.php   loader, page import, titles and meta descriptions
  manifest.json                  every page: slug, title, doc_title (SEO title <= 60), description (<= 160),
                                 optional "service" {name,type,area} and "faqs" [[q,a]] for structured data
  pages/NN-<slug>.json           Elementor content of each page
  templates/                     header and footer
  includes/seo.php               social tags, JSON-LD (business, page, breadcrumbs, service, FAQ),
                                 noindex for the 404 page, sitemap clean-up
  includes/*.php                 forms, popups, chat, enquiries, setup gate
  assets/                        CSS, JS, images
tools/build-zip.sh               builds dist/prism-works-site-builder.zip
docs/                            NOT committed: SEO playbook, GSC analyses, SEO rules, drafts, progress
```

## Release

1. Edit the plugin.
2. Run `sh tools/build-zip.sh`.
3. Upload `dist/prism-works-site-builder.zip` (Plugins > Add New > Upload Plugin > Replace current).

Uploading never changes existing pages. New pages added to manifest.json are created by the plugin's import, which skips existing pages. Changed content of existing pages needs "Reset all pages to original design", which backs up first and can be undone.
