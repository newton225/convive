import { Form, Head, router } from '@inertiajs/react';
import { ChevronDown, Mail, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import CancelInvitationModal from '@/components/cancel-invitation-modal';
import DeleteTenantModal from '@/components/delete-tenant-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import InviteMemberModal from '@/components/invite-member-modal';
import RemoveMemberModal from '@/components/remove-member-modal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { edit, update } from '@/routes/tenants';
import { update as updateMember } from '@/routes/tenants/members';
import { translate, useTranslation } from '@/hooks/use-translation';
import { can, Permission } from '@/lib/permissions';
import type {
    ProfileOption,
    Tenant,
    TenantInvitation,
    TenantMember,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: Tenant;
    members: TenantMember[];
    invitations: TenantInvitation[];
    permissions: TenantPermissions;
    availableProfiles: ProfileOption[];
};

export default function TenantEdit({
    tenant,
    members,
    invitations,
    permissions,
    availableProfiles,
}: Props) {
    const { t } = useTranslation();
    const getInitials = useInitials();

    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [removeMemberDialogOpen, setRemoveMemberDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<TenantMember | null>(
        null,
    );
    const [cancelInvitationDialogOpen, setCancelInvitationDialogOpen] =
        useState(false);
    const [invitationToCancel, setInvitationToCancel] =
        useState<TenantInvitation | null>(null);

    const pageTitle = useMemo(
        () =>
            can(permissions, Permission.TenantBranding)
                ? t('tenants.edit.title', { name: tenant.name })
                : t('tenants.edit.read_only_title', { name: tenant.name }),
        [can(permissions, Permission.TenantBranding), tenant.name, t],
    );

    const assignProfile = (member: TenantMember, profileId: number) => {
        router.visit(updateMember([tenant.slug, member.id]), {
            data: { profile_id: profileId },
            preserveScroll: true,
        });
    };

    const confirmRemoveMember = (member: TenantMember) => {
        setMemberToRemove(member);
        setRemoveMemberDialogOpen(true);
    };

    const confirmCancelInvitation = (invitation: TenantInvitation) => {
        setInvitationToCancel(invitation);
        setCancelInvitationDialogOpen(true);
    };

    return (
        <>
            <Head title={pageTitle} />

            <h1 className="sr-only">{pageTitle}</h1>

            <div className="flex flex-col space-y-10">
                <div className="space-y-6">
                    {can(permissions, Permission.TenantBranding) ? (
                        <>
                            <Heading
                                variant="small"
                                title={t('tenants.settings.title')}
                                description={t('tenants.settings.description')}
                            />

                            <Form
                                {...update.form(tenant.slug)}
                                setDefaultsOnSuccess
                                className="space-y-6"
                            >
                                {({ errors, processing, isDirty }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                {t(
                                                    'tenants.settings.name_label',
                                                )}
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                data-test="tenant-name-input"
                                                defaultValue={tenant.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <SubmitButton
                                                data-test="tenant-save-button"
                                                processing={processing}
                                                dirty={isDirty}
                                            >
                                                {t('common.actions.save')}
                                            </SubmitButton>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </>
                    ) : (
                        <>
                            <Heading variant="small" title={tenant.name} />
                        </>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title={t('tenants.members.title')}
                            description={
                                can(permissions, Permission.TeamInvite)
                                    ? t('tenants.members.description')
                                    : ''
                            }
                        />

                        <div className="flex items-center gap-2">
                            {can(permissions, Permission.TeamInvite) ? (
                                <Button
                                    data-test="invite-member-button"
                                    onClick={() => setInviteDialogOpen(true)}
                                >
                                    <UserPlus /> {t('tenants.members.invite')}
                                </Button>
                            ) : null}
                        </div>
                    </div>

                    <div className="space-y-3">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                data-test="member-row"
                                className="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-4">
                                    <Avatar className="h-10 w-10">
                                        {member.avatar ? (
                                            <AvatarImage
                                                src={member.avatar}
                                                alt={member.name}
                                            />
                                        ) : null}
                                        <AvatarFallback>
                                            {getInitials(member.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div className="font-medium">
                                            {member.name}
                                        </div>
                                        <div className="text-muted-foreground text-sm">
                                            {member.email}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {!member.isOwner &&
                                    can(
                                        permissions,
                                        Permission.ProfilesManage,
                                    ) ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    data-test="member-profile-trigger"
                                                >
                                                    {member.profileName}
                                                    <ChevronDown className="ml-2 h-4 w-4 opacity-50" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent>
                                                {availableProfiles.map(
                                                    (profile) => (
                                                        <DropdownMenuItem
                                                            key={profile.id}
                                                            data-test="member-profile-option"
                                                            onSelect={() =>
                                                                assignProfile(
                                                                    member,
                                                                    profile.id,
                                                                )
                                                            }
                                                        >
                                                            {profile.name}
                                                        </DropdownMenuItem>
                                                    ),
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : (
                                        <Badge variant="secondary">
                                            {member.profileName}
                                        </Badge>
                                    )}

                                    {!member.isOwner &&
                                    can(permissions, Permission.TeamRemove) ? (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="member-remove-button"
                                                        onClick={() =>
                                                            confirmRemoveMember(
                                                                member,
                                                            )
                                                        }
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        {t(
                                                            'tenants.members.remove',
                                                        )}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {invitations.length > 0 ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title={t('tenants.invitations.title')}
                            description={t('tenants.invitations.description')}
                        />

                        <div className="space-y-3">
                            {invitations.map((invitation) => (
                                <div
                                    key={invitation.code}
                                    data-test="invitation-row"
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-full">
                                            <Mail className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                {invitation.profileName}
                                            </div>
                                        </div>
                                    </div>

                                    {can(permissions, Permission.TeamInvite) ? (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="invitation-cancel-button"
                                                        onClick={() =>
                                                            confirmCancelInvitation(
                                                                invitation,
                                                            )
                                                        }
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        {t(
                                                            'tenants.invitations.cancel',
                                                        )}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                {can(permissions, Permission.TenantLegal) &&
                !tenant.isPersonal ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title={t('tenants.delete.title')}
                            description={t('tenants.delete.description')}
                        />
                        <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                            <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                                <p className="font-medium">
                                    {t('tenants.delete.warning_title')}
                                </p>
                                <p className="text-sm">
                                    {t('tenants.delete.warning_body')}
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                data-test="delete-tenant-button"
                                onClick={() => setDeleteDialogOpen(true)}
                            >
                                {t('tenants.actions.delete')}
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>

            {can(permissions, Permission.TeamInvite) ? (
                <InviteMemberModal
                    tenant={tenant}
                    availableProfiles={availableProfiles}
                    open={inviteDialogOpen}
                    onOpenChange={setInviteDialogOpen}
                />
            ) : null}

            <RemoveMemberModal
                tenant={tenant}
                member={memberToRemove}
                open={removeMemberDialogOpen}
                onOpenChange={setRemoveMemberDialogOpen}
            />

            <CancelInvitationModal
                tenant={tenant}
                invitation={invitationToCancel}
                open={cancelInvitationDialogOpen}
                onOpenChange={setCancelInvitationDialogOpen}
            />

            {can(permissions, Permission.TenantLegal) && !tenant.isPersonal ? (
                <DeleteTenantModal
                    tenant={tenant}
                    open={deleteDialogOpen}
                    onOpenChange={setDeleteDialogOpen}
                />
            ) : null}
        </>
    );
}

TenantEdit.layout = (props: {
    tenant: { name: string; slug: string };
    translations: Translations;
}) => ({
    narrow: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'navigation.team'),
            href: edit(props.tenant.slug),
        },
    ],
});
