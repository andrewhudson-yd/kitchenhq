<svg class="ui-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
@case('inventory')<rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M5 9.5h14M8.5 5.5v1.5M8.5 12.5v3"/>@break
@case('fridge')<rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M5 9.5h14M8.5 5.5v1.5M8.5 12.5v3"/>@break
@case('freezer')<path d="M12 2v20M4.5 6.5l15 11M19.5 6.5l-15 11M9 4l3 2 3-2M9 20l3-2 3 2"/>@break
@case('pantry')<path d="M4 3.5h16v17H4zM12 3.5v17M9.5 11v2M14.5 11v2"/>@break
@case('planner')<rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M7.5 3v4M16.5 3v4M3.5 10h17"/>@break
@case('shopping')<path d="M4 6h2l2 10h10l2-7H7"/><circle cx="9.5" cy="19.5" r="1.3"/><circle cx="16.5" cy="19.5" r="1.3"/>@break
@case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('check')<path d="m5 12.5 4.5 4.5L19 7.5"/>@break
@case('x')<path d="M6 6l12 12M18 6 6 18"/>@break
@case('more')<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>@break
@case('logout')<path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M14 8l4 4-4 4M8 12h13"/>@break
@endswitch
</svg>
