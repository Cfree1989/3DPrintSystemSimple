# 📘 Apple-Style Dashboard UI/UX Reference Guide

## 1. Design Philosophy (Foundational Rules)
**Apply globally — every layout, interaction, and element must follow:**

| Principle        | Implementation Notes |
|------------------|-----------------------|
| **Clarity**      | Prioritize legibility, white space, and intuitive layouts. Avoid clutter or unnecessary decoration. |
| **Deference**    | UI recedes; content leads. Avoid flashy containers or heavy outlines. Use motion subtly. |
| **Depth**        | Employ translucent panels, shadows, and layers to establish spatial relationships. No more than 3 layers. |
| **Consistency**  | Repeat patterns and layout behavior. Use standardized icons, spacing, and type hierarchy throughout. |
| **Simplicity**   | Strip to essential actions and elements. Never overload a screen. |
| **Efficiency**   | Reduce taps/clicks to complete tasks. Support touch gestures and keyboard navigation. |
| **Accessibility**| Meet WCAG 2.1 AA minimums. Support Dynamic Type, colorblind-friendly cues, and reduced motion options. |

---

## 2. Layout & Spacing

| Directive                              | Implementation Tooling |
|----------------------------------------|-------------------------|
| Use an **8-point grid system**         | Tailwind spacing units (`p-2`, `mt-4`, etc. map to 8/16/32px) |
| Respect **safe areas** and **margins** | Use consistent padding/margin (`px-4`, `md:py-6`) and `max-w-screen-lg` containers |
| Maintain **visual hierarchy**          | Separate key regions with ample whitespace (`space-y-6`, `divide-y`) |
| Responsive design from **mobile-first**| Use Tailwind responsive prefixes (`sm:`, `md:`, `lg:`), Flexbox, and CSS Grid |

---

## 3. Typography

| Rule                                         | Tailwind / HTML Guidance |
|---------------------------------------------|---------------------------|
| Use **SF Pro** or system font stack         | `font-sans` with custom fallback stack: `system-ui, -apple-system, BlinkMacSystemFont` |
| Respect **optical sizes**                   | Use `text-sm` for sub-labels, `text-lg` or `text-xl` for section headers |
| No Thin/Ultralight fonts                    | Stick to `font-medium`, `font-semibold`, `font-bold` |
| Support **Dynamic Type**                    | Avoid fixed px values; use `em`, `rem`, or `%` for font size and spacing |
| Ensure **clear hierarchy**                  | Apply heading structure: `h1` (xl), `h2` (lg), body (base) |

---

## 4. Color & Contrast

| Guideline                                    | Implementation |
|---------------------------------------------|----------------|
| Use **purpose-driven color**                | Tailwind `text-blue-600` for primary actions, `text-red-600` for destructive |
| Maintain **min. 4.5:1 contrast ratio**      | Use `bg-white`, `text-gray-900` combos; validate via contrast checker |
| Support **dark/light mode**                 | Use `dark:` variants in Tailwind; test both schemes visually |
| Never use color as sole indicator           | Add icons, labels, or borders to differentiate states |
| Use **limited palette**                     | Stick to grayscale + 2–3 accent colors; no rainbow UIs |

---

## 5. Depth, Shadows, and Motion

| Element                        | Implementation |
|--------------------------------|----------------|
| **Glassmorphism** panels       | Use `bg-white/60 backdrop-blur-md shadow-lg rounded-xl` |
| **Multi-layered shadows**      | Apply layered `box-shadow` via Tailwind or custom `shadow-[...]` utilities |
| **Max 3 layers of depth**      | Use z-index discipline: content (base), modal (higher), alert (topmost) |
| **Motion** must be subtle, fast| Use `transition-all duration-200 ease-in-out`; avoid long or bouncy animations |
| Prefer fades over 3D motion    | Use `opacity-0/100`, `scale-95/100`, not 3D transforms unless deliberate |
| All motion must be **optional**| Respect `prefers-reduced-motion` media query for animation disabling |

---

## 6. Interactive Elements (Buttons, Tabs, etc.)

| Component      | Key Traits |
|----------------|------------|
| **Buttons**    | `min-w-[44px] min-h-[44px] px-4 py-2 rounded-full bg-blue-600 text-white` |
| **Tabs**       | Persistent nav for top sections; use icons (SF Symbols or `lucide-react`) and short labels |
| **Sidebars**   | Use `flex-col w-[260px] bg-white/70 backdrop-blur-md` with toggles |
| **Modals**     | Simple, centered, shadowed: `fixed inset-0 flex items-center justify-center` with `bg-white rounded-xl p-6` |
| **Alerts**     | Use sparingly, with clear icons (`text-red-600`) and accessible titles |
| **Form Fields**| `rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500` |

---

## 7. Icons & Images

| Rule                                 | Notes |
|--------------------------------------|-------|
| Use **SF Symbols** or SVGs           | Use `lucide-react` or `heroicons` as scalable, consistent alternatives |
| Support **@2x/@3x** images           | Prefer SVGs; raster images must support Retina via `srcset` |
| Maintain **aspect ratio** always     | Use `aspect-[x/y]` classes or `object-contain` |
| Icons must be **semantically clear** | Use universally recognizable icons for Search, Settings, Filters, etc. |

---

## 8. JavaScript Behavior

| Goal                           | Implementation |
|--------------------------------|----------------|
| **Fluid transitions**          | Use GSAP or native `requestAnimationFrame` for fine-tuned motion |
| **Interactive filtering**      | Use lightweight JavaScript to update views without full reload |
| **Touch-friendly interactions**| Ensure click + `touchstart` event support; use `pointer-events` and gesture libraries if needed |
| **No hard dependencies**       | Keep JS modular and testable; progressive enhancement > monolith |

---

## 9. Accessibility Checklist

| Requirement                     | Ensure via |
|----------------------------------|------------|
| Controls ≥ **44x44pt**          | Use `min-h-[44px] min-w-[44px]` |
| High contrast (≥ 4.5:1)         | Use WebAIM contrast checker or browser DevTools |
| Support **Dynamic Type**        | Use relative units: `rem`, `em`; avoid hardcoded font px |
| Use **ARIA roles** and labels  | `aria-label`, `role="dialog"`, etc. for all interactive components |
| Enable keyboard navigation      | `tabindex`, `:focus-visible`, `Enter/Space` key activation |
| Avoid motion triggers           | `@media (prefers-reduced-motion)` to suppress animation |

---

## 10. Tooling & Libraries (Approved for Use)

| Tool/Library      | Purpose |
|-------------------|---------|
| **Tailwind CSS**  | Layout, spacing, responsive design, effects |
| **Lucide / Heroicons** | Iconography |
| **GSAP or Framer Motion** | Advanced animation control |
| **SF Pro / System Fonts** | Typography baseline |
| **apple-svelte** (if using Svelte) | Apple-style components |
| **macos-tailwind** (HTML demo) | Visual fidelity reference |
| **Contrast Checkers** | Ensure color accessibility (e.g., WebAIM) |

---
