# Design System Specification: Tactical Emergency Command



## 1. Overview & Creative North Star

**Creative North Star: "Kinetic Command"**



This design system moves beyond the "app" aesthetic and into the realm of high-stakes mission control. It is designed for the split-second decision-making required in emergency response. The visual language rejects the friendly, rounded "SaaS" look in favor of a bespoke, editorial precision that feels both authoritative and urgent.



We achieve this through **Kinetic Command**: a layout strategy that uses intentional asymmetry, high-contrast typography scales, and a "HUD-first" (Heads-Up Display) philosophy. By leaning into deep, obsidian surfaces and the visceral intensity of RAL 3000 and 3024, we create an environment where data doesn't just sit on a screen—it commands attention. This is a system for operators, not users.



## 2. Colors & Atmospheric Depth

The palette is rooted in the high-visibility heritage of emergency services. We use deep shadows to provide a void-like backdrop, allowing our tactical reds to "pierce" the UI.



### The "No-Line" Rule

To maintain a high-end, bespoke feel, **1px solid borders are strictly prohibited for sectioning components.** Structural boundaries must be defined through background color shifts. For example, a global navigation bar should use `surface_container_low` against a `surface` background. If you need to separate content, use the `surface_container` tiers to create tonal logic rather than physical lines.



### Surface Hierarchy & Layering

Treat the UI as a series of physical, stacked layers.

- **Base Layer:** `surface` (#131313) or `surface_container_lowest` (#0e0e0e) for the deep background.

- **Mid-Tier:** `surface_container` (#201f1f) for primary content areas.

- **Top-Tier:** `surface_container_highest` (#353534) for active or interactive panels.



### The "Glass & Kinetic" Rule

For floating overlays, system alerts, or "always-on" telemetry, use **Glassmorphism**. Apply a semi-transparent `surface_container_high` with a backdrop blur. This allows the high-vis reds of the alert system to glow through the UI layers, maintaining context even when a modal is active.



### Signature Textures

Main CTAs and high-priority alerts should utilize subtle linear gradients transitioning from `primary` (#ffb4a8) to `primary_container` (#af2b1e). This adds a "machined" polish to the UI that flat colors cannot replicate.



## 3. Typography

The typography strategy creates a tension between technical utility and editorial impact.



- **Display & Headlines (Space Grotesk):** This font brings a geometric, futuristic precision. Use `display-lg` and `display-md` for critical status numbers or high-level alerts. The technical nature of Space Grotesk mirrors the tactical RAL influence.

- **Body & Titles (Inter):** Inter is the workhorse. It provides maximum legibility for incident reports and telemetry data.

- **Visual Hierarchy:** Create a "Data-Dread" effect by using extreme scale. A very large `display-lg` alert code should sit next to a very small, uppercase `label-sm` in `on_surface_variant` to emphasize the gravity of the data.



## 4. Elevation & Depth

We reject the standard Material Design shadow. Depth is achieved through **Tonal Layering**.



- **The Layering Principle:** Place a `surface_container_lowest` card on a `surface_container_low` section to create a "recessed" look. Conversely, stack `surface_container_high` on `surface` for a natural lift.

- **Ambient Shadows:** When an element must float (e.g., a critical override button), use an extra-diffused shadow with a 6% opacity. The shadow color should be a deep red-tinted black to mimic the ambient glow of the luminous red primary colors.

- **The "Ghost Border" Fallback:** If accessibility requires a border, use the `outline_variant` token at 15% opacity. It should feel like a suggestion of a line, not a container.



## 5. Components



### Buttons

- **Primary (Emergency):** Solid `primary_container` (#af2b1e) with `on_primary` text. Use `DEFAULT` (0.25rem) roundedness for a sharp, tactical feel.

- **Secondary:** `surface_container_high` with a `primary` "Ghost Border."

- **Kinetic State:** On hover, primary buttons should shift toward the luminous `secondary_container` (#ff5540) to simulate an "arming" sequence.



### Input Fields

- Avoid boxed inputs. Use a `surface_container_highest` background with a 2px bottom-accent in `outline`.

- **Error State:** Transitions the bottom accent to `error` (#ffb4ab) with a subtle glow effect (box-shadow) using the `on_error` color.



### Tactical Cards & Lists

- **Rule:** No divider lines. Use `6` (1.3rem) spacing from the scale to separate list items.

- **Active State:** Instead of a border, use a vertical "Status Strip" (4px wide) on the left edge of the card using `tertiary` (#ebc300) for warnings or `primary` for emergencies.



### Additional Component: The Telemetry HUD

A custom component for this system. A transparent container with a `surface_variant` (20% opacity) background and a `label-sm` header. This component should display live data feeds (GPS, vitals, time-to-arrival) using high-contrast `display-sm` Space Grotesk.



## 6. Do’s and Don’ts



### Do

- **Do** embrace asymmetry. Align large display text to the left while keeping telemetry data right-justified.

- **Do** use the `tertiary` (#ebc300) color sparingly—only for "Caution" states to keep the focus on the primary reds.

- **Do** use the Spacing Scale religiously. Consistent gaps of `4` (0.9rem) or `8` (1.75rem) create the rhythmic layout of a professional instrument.



### Don't

- **Don't** use standard "blue" for links. All interactive elements must live within the Red/Gold/Neutral spectrum.

- **Don't** use `xl` roundedness. Large rounds (pills) feel too consumer-friendly. Stick to `sm` and `md` to maintain a "hardened" hardware feel.

- **Don't** clutter. If a piece of data isn't vital for the emergency response, move it to a `surface_container_lowest` background to de-emphasize it.