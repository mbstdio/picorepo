<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <Link
                        :href="route('repositories.index')"
                        class="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1 mb-2"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                        Repositories
                    </Link>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold">
                            {{ repository.name }}
                        </h1>
                        <Badge
                            :variant="
                                repository.type === 'public'
                                    ? 'secondary'
                                    : 'outline'
                            "
                            >{{ repository.type }}</Badge
                        >
                    </div>
                    <p
                        v-if="repository.description"
                        class="text-muted-foreground mt-1"
                    >
                        {{ repository.description }}
                    </p>
                </div>

                <!-- Actions Edit / Delete -->
                <div v-if="repository.can.update" class="flex gap-2 shrink-0">
                    <Link :href="route('repositories.edit', repository.slug)">
                        <Button variant="outline">Edit</Button>
                    </Link>
                    <Button
                        v-if="repository.can.delete"
                        variant="destructive"
                        @click="confirmDelete"
                    >
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Tab navigation -->
            <ButtonGroup>
                <Button
                    :variant="activeTab === 'packages' ? 'default' : 'outline'"
                    @click="activeTab = 'packages'"
                >
                    Packages ({{ repository.packages.length }})
                </Button>
                <Button
                    v-if="repository.can.manage_access"
                    :variant="activeTab === 'access' ? 'default' : 'outline'"
                    @click="activeTab = 'access'"
                >
                    Access ({{ repository.users.length }})
                </Button>
                <Button
                    :variant="activeTab === 'composer' ? 'default' : 'outline'"
                    @click="activeTab = 'composer'"
                >
                    Composer Config
                </Button>
            </ButtonGroup>

            <div>
                <!-- Packages tab -->
                <div v-if="activeTab === 'packages'" class="space-y-4">
                    <!-- Sub-header with "Add Package" -->
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            {{ repository.packages.length }} package{{
                                repository.packages.length !== 1 ? "s" : ""
                            }}
                            in this repository
                        </p>
                        <Link
                            v-if="repository.can.manage_versions"
                            :href="
                                route(
                                    'repositories.packages.create',
                                    repository.slug,
                                )
                            "
                        >
                            <Button>Add Package</Button>
                        </Link>
                    </div>

                    <div
                        v-if="repository.packages.length === 0"
                        class="text-center py-12 text-muted-foreground border rounded-lg"
                    >
                        No packages yet.
                        <Link
                            v-if="repository.can.manage_versions"
                            :href="
                                route(
                                    'repositories.packages.create',
                                    repository.slug,
                                )
                            "
                            class="text-primary ml-1 hover:underline"
                        >
                            Add a package.
                        </Link>
                    </div>

                    <div v-else class="space-y-3">
                        <Card
                            v-for="pkg in repository.packages"
                            :key="pkg.id"
                            class="hover:bg-accent/30 transition-colors"
                        >
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <CardTitle class="text-base font-mono">
                                            <Link
                                                :href="
                                                    route(
                                                        'repositories.packages.show',
                                                        [
                                                            repository.slug,
                                                            pkg.id,
                                                        ],
                                                    )
                                                "
                                                class="hover:underline"
                                            >
                                                {{ pkg.full_name }}
                                            </Link>
                                        </CardTitle>
                                        <CardDescription
                                            v-if="pkg.description"
                                            class="mt-0.5"
                                            >{{
                                                pkg.description
                                            }}</CardDescription
                                        >
                                    </div>
                                    <Badge
                                        variant="outline"
                                        class="shrink-0 ml-4"
                                    >
                                        {{ pkg.versions.length }} version{{
                                            pkg.versions.length !== 1 ? "s" : ""
                                        }}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent
                                v-if="pkg.versions.length > 0"
                                class="pt-0"
                            >
                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-for="v in pkg.versions.slice(0, 5)"
                                        :key="v.id"
                                        variant="secondary"
                                        class="font-mono text-xs"
                                    >
                                        {{ v.version }}
                                    </Badge>
                                    <span
                                        v-if="pkg.versions.length > 5"
                                        class="text-xs text-muted-foreground self-center"
                                    >
                                        +{{ pkg.versions.length - 5 }} more
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Access tab -->
                <div v-if="activeTab === 'access'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            {{ repository.users.length }} member{{
                                repository.users.length !== 1 ? "s" : ""
                            }}
                        </p>
                        <Link
                            :href="
                                route(
                                    'repositories.users.index',
                                    repository.slug,
                                )
                            "
                        >
                            <Button size="sm" variant="outline"
                                >Manage Access</Button
                            >
                        </Link>
                    </div>
                    <div class="border rounded-lg overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="user in repository.users"
                                    :key="user.id"
                                >
                                    <TableCell class="font-medium">{{
                                        user.name
                                    }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{
                                        user.email
                                    }}</TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                user.role === 'owner'
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                            >{{ user.role }}</Badge
                                        >
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Composer config tab -->
                <div v-if="activeTab === 'composer'" class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        Add the following to the
                        <code class="bg-muted px-1 py-0.5 rounded text-xs"
                            >repositories</code
                        >
                        array in your
                        <code class="bg-muted px-1 py-0.5 rounded text-xs"
                            >composer.json</code
                        >:
                    </p>
                    <div class="relative">
                        <pre
                            class="bg-muted rounded-lg p-4 text-sm font-mono overflow-x-auto"
                            >{{ composerConfigJson }}</pre
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            class="absolute top-2 right-2"
                            @click="copyConfig"
                        >
                            {{ copied ? "Copied!" : "Copy" }}
                        </Button>
                    </div>
                    <Alert v-if="repository.type === 'private'">
                        <AlertDescription>
                            This is a <strong>private</strong> repository. Users
                            need a valid API token to access it via Composer.
                            Generate tokens in
                            <Link
                                :href="route('api-tokens.index')"
                                class="underline"
                                >Profile → API Tokens</Link
                            >. <br /><br />
                            Add to your
                            <code class="bg-muted px-1 py-0.5 rounded text-xs"
                                >auth.json</code
                            >:
                            <pre
                                class="bg-muted rounded mt-2 p-3 text-xs font-mono"
                                >{{ authJson }}</pre
                            >
                        </AlertDescription>
                    </Alert>
                </div>
            </div>

            <!-- Delete confirmation dialog -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Repository</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete
                            <strong>{{ repository.name }}</strong
                            >? This will also delete all packages and versions.
                            This action cannot be undone.
                        </DialogDescription>
                        <p v-if="archiveError" class="text-sm text-destructive">
                            {{ archiveError }}
                        </p>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="showDeleteDialog = false"
                            >Cancel</Button
                        >
                        <Button variant="destructive" @click="deleteRepository"
                            >Delete</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { ButtonGroup } from "@/components/ui/button-group";
import { Badge } from "@/components/ui/badge";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

const props = defineProps({
    repository: Object,
});

const activeTab = ref("packages");
const showDeleteDialog = ref(false);
const copied = ref(false);
const archiveError = ref(null);

const composerConfigJson = computed(() =>
    JSON.stringify(props.repository.composer_config, null, 4),
);

const authJson = computed(() => {
    const url = new URL(props.repository.composer_url);
    return JSON.stringify(
        {
            "http-basic": {
                [url.host]: { username: "YOUR_API_TOKEN", password: "" },
            },
        },
        null,
        4,
    );
});

function copyConfig() {
    navigator.clipboard.writeText(composerConfigJson.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function confirmDelete() {
    archiveError.value = null;
    showDeleteDialog.value = true;
}

function deleteRepository() {
    router.delete(route("repositories.destroy", props.repository.slug), {
        onError: (errors) => (archiveError.value = errors.archive),
    });
}
</script>
