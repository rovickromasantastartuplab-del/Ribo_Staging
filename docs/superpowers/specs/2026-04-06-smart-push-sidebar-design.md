# Smart Push CRM Sidebar Design

## Goal
Transition the Conversation Hub's CRM context sidebar into a responsive “Smart Push” layout so that on xl+ screens the sidebar sits alongside the email thread instead of overlaying it, while preserving the current overlay/backdrop behavior on lg and smaller breakpoints.

## Context
- Pane 4 (CRM context sidebar) is currently rendered as an absolute right-offset overlay with a backdrop triggered by `showContactSidebar`.
- Power users need to see the CRM data and the email thread simultaneously when screen width is at least xl (1280px) so 600px remains available for the email content.
- On smaller screens the existing overlay/backdrop experience should stay unchanged.

## Approach
1. Keep the absolute overlay/backdrop for `lg` and below. Preserve the existing `w-full md:w-[300px] max-w-[88vw]` sizing plus the `min-w-0` flex adjustments inside the sidebar content.
2. At `xl` and wider, change the sidebar container to `relative flex-shrink-0` so it becomes a peer flex sibling to the email pane, add `xl:border-l` and remove the overlay/backdrop (hide it via `xl:hidden`), and keep the width locked at `w-[300px]`.
3. Animate both the sidebar and the main email pane with `transition-all duration-300 ease-in-out` to deliver the requested “Smart Push” toggle effect across the breakpoint. The main thread pane should shrink/grow gracefully when the sidebar enters/exits. Also ensure any 100vh-calc heights stay aligned.

## Details
- Update the DOM structure around Pane 4 so it can sit inside the main flex grid rather than floating above it on xl+. The panel should remain at the end of the layout (after the email column) and keep its just-in-time rendering from `showContactSidebar`.
- Add `xl:relative xl:flex-shrink-0 xl:border-l xl:shadow-none` (and `transition-all duration-300 ease-in-out w-[300px]`) to the sidebar container. Include `xl:hidden` on the existing backdrop.
- Apply `transition-all duration-300 ease-in-out` to the parent container wrapping the email thread (Pane 3), ensuring its width can animate as the sidebar toggles.
- Test height calculations referencing `calc(100vh - X)` by previewing the layout in Storybook or the running app to confirm there are no overflow issues with the new flex sibling behavior.

## Validation
- On `xl` and above verify the CRM sidebar pushes the email pane horizontally without overlapping, and that the backdrop is absent.
- On `lg` and below confirm the overlay/backdrop still appears and transitions reasonably with the provided duration/ease.
- Run any relevant CSS/resizing smoke tests (manual visual checks) to ensure the `min-w-0` fix avoids overflow when names are long.

## Next Steps
- After the spec is reviewed, feed the instructions into `superpowers:writing-plans` to break the actual DOM/Tailwind updates into tasks.
