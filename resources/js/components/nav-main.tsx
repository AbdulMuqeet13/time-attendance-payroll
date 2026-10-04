import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

function NavMainItem({ item }: { item: NavItem }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    if (!item.children?.length) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={isCurrentUrl(item.href)}
                    tooltip={{ children: item.title }}
                >
                    <Link href={item.href} prefetch>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    const isChildActive = item.children.some((child) =>
        isCurrentUrl(child.href),
    );
    const isParentActive = isCurrentOrParentUrl(item.href);
    const shouldOpen = isChildActive || isParentActive;

    return (
        <Collapsible defaultOpen={shouldOpen} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        isActive={isChildActive}
                        tooltip={{ children: item.title }}
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.children.map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                {child.disabled ? (
                                    <SidebarMenuSubButton className="pointer-events-none text-muted-foreground/50">
                                        <span>{child.title}</span>
                                        <span className="ml-auto text-[10px] uppercase">
                                            Soon
                                        </span>
                                    </SidebarMenuSubButton>
                                ) : (
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={isCurrentUrl(child.href)}
                                    >
                                        <Link href={child.href} prefetch>
                                            <span>{child.title}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                )}
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({
    items,
    label = 'Navigation',
}: {
    items: NavItem[];
    label?: string;
}) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <NavMainItem key={item.title} item={item} />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
