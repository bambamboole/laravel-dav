import { defineConfig } from "astro/config";
import starlight from "@astrojs/starlight";
import tailwindcss from "@tailwindcss/vite";

const site = process.env.SITE_URL || "https://bambamboole.github.io";
const base = process.env.BASE_PATH || "/laravel-dav";
const viteCacheSuffix = process.argv.includes("build") ? "build" : "dev";

export default defineConfig({
  site,
  base,
  srcDir: "./docs",
  devToolbar: {
    enabled: false,
  },
  integrations: [
    starlight({
      title: "LaravelDAV",
      description: "CalDAV and CardDAV server for Laravel, powered by sabre/dav.",
      logo: {
        light: "./docs/assets/logo.svg",
        dark: "./docs/assets/logo-dark.svg",
        replacesTitle: true,
      },
      social: [
        {
          icon: "github",
          label: "GitHub",
          href: "https://github.com/bambamboole/laravel-dav",
        },
      ],
      editLink: {
        baseUrl: "https://github.com/bambamboole/laravel-dav/edit/main/",
      },
      customCss: ["./docs/styles/global.css"],
      sidebar: [
        {
          label: "Getting Started",
          items: [
            { label: "Introduction", link: "/" },
            { label: "Installation", link: "/getting-started/installation/" },
            { label: "Owner Setup", link: "/getting-started/owner-setup/" },
            { label: "Credentials", link: "/getting-started/credentials/" },
            { label: "Configuration", link: "/getting-started/configuration/" },
            { label: "Client Connection", link: "/getting-started/client-connection/" },
          ],
        },
        {
          label: "Core Concepts",
          items: [
            { label: "Principals and Owners", link: "/concepts/principals-and-owners/" },
            { label: "Calendars", link: "/concepts/calendars/" },
            { label: "Address Books", link: "/concepts/address-books/" },
            { label: "Sync Tokens", link: "/concepts/sync-tokens/" },
            { label: "Raw Data and DTOs", link: "/concepts/raw-data-and-dtos/" },
          ],
        },
        {
          label: "Guides",
          items: [
            { label: "Create Collections", link: "/guides/create-collections/" },
            { label: "Calendar Sharing", link: "/guides/calendar-sharing/" },
            { label: "Calendar Proxy Delegation", link: "/guides/calendar-proxy-delegation/" },
            { label: "Calendar Subscriptions", link: "/guides/calendar-subscriptions/" },
            { label: "Calendar Objects", link: "/guides/read-and-write-calendar-objects/" },
            { label: "Contacts", link: "/guides/read-and-write-contacts/" },
            { label: "Scheduling", link: "/guides/scheduling/" },
            { label: "Free/Busy and Availability", link: "/guides/free-busy-and-availability/" },
            { label: "Reacting to Changes", link: "/guides/reacting-to-changes/" },
            { label: "Custom Models", link: "/guides/custom-models/" },
          ],
        },
        {
          label: "Protocol Support",
          items: [
            { label: "WebDAV", link: "/protocol/webdav/" },
            { label: "CalDAV", link: "/protocol/caldav/" },
            { label: "CardDAV", link: "/protocol/carddav/" },
            { label: "Discovery", link: "/protocol/discovery/" },
            { label: "Authentication", link: "/protocol/authentication/" },
            { label: "Locks", link: "/protocol/locks/" },
            { label: "Property Storage", link: "/protocol/property-storage/" },
            { label: "Exports", link: "/protocol/exports/" },
            { label: "Compatibility", link: "/protocol/compatibility/" },
          ],
        },
      ],
    }),
  ],
  vite: {
    cacheDir: `node_modules/.vite-${viteCacheSuffix}`,
    plugins: [tailwindcss()],
  },
});
