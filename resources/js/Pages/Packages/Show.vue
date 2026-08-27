<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <Link
                        :href="route('repositories.show', repository.slug)"
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
                        {{ repository.name }}
                    </Link>
                    <h1 class="text-2xl font-bold font-mono">
                        {{ package_.full_name }}
                    </h1>
                    <p
                        v-if="package_.description"
                        class="text-muted-foreground mt-1"
                    >
                        {{ package_.description }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        v-if="package_.can.manage"
                        :href="
                            route('repositories.packages.edit', [
                                repository.slug,
                                package_.id,
                            ])
                        "
                    >
                        <Button variant="outline">Edit Package</Button>
                    </Link>
                    <Link
                        v-if="package_.can.manage"
                        :href="
                            route('repositories.packages.versions.create', [
                                repository.slug,
                                package_.id,
                            ])
                        "
                    >
                        <Button>Add Version</Button>
                    </Link>
                    <Button
                        v-if="package_.can.delete"
                        variant="destructive"
                        @click="showDeleteDialog = true"
                        >Delete Package</Button
                    >
                </div>
            </div>

            <!-- Versions -->
            <div>
                <h2 class="text-lg font-semibold mb-3">Versions</h2>
                <div
                    v-if="package_.versions.length === 0"
                    class="text-center py-12 text-muted-foreground border rounded-lg"
                >
                    No versions yet.
                    <Link
                        v-if="package_.can.manage"
                        :href="
                            route('repositories.packages.versions.create', [
                                repository.slug,
                                package_.id,
                            ])
                        "
                        class="text-primary ml-1 hover:underline"
                        >Add the first version.</Link
                    >
                </div>
                <div v-else class="border rounded-lg overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Version</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Storage</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Added</TableHead>
                                <TableHead class="w-32"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="v in package_.versions"
                                :key="v.id"
                            >
                                <TableCell class="font-mono font-medium">{{
                                    v.version
                                }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="text-xs">{{
                                        v.type
                                    }}</Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="secondary"
                                        class="text-xs"
                                        >{{ v.disk }}</Badge
                                    >
                                </TableCell>
                                <TableCell
                                    class="text-muted-foreground text-sm max-w-xs truncate"
                                    >{{ v.description || "—" }}</TableCell
                                >
                                <TableCell
                                    class="text-muted-foreground text-sm"
                                    >{{ formatDate(v.created_at) }}</TableCell
                                >
                                <TableCell>
                                    <Link
                                        v-if="package_.can.manage"
                                        :href="route('repositories.packages.versions.edit', [repository.slug, package_.id, v.id])"
                                    >
                                        <Button variant="ghost" size="sm">Edit</Button>
                                    </Link>
                                    <Button
                                        v-if="package_.can.manage"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDeleteVersion(v)"
                                    >
                                        Delete
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Delete version dialog -->
            <Dialog v-model:open="showDeleteVersionDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle
                            >Delete Version
                            {{ selectedVersion?.version }}</DialogTitle
                        >
                        <DialogDescription>
                            This will permanently delete version
                            <strong>{{ selectedVersion?.version }}</strong> of
                            <strong>{{ package_.full_name }}</strong> and its
                            zip file.
                        </DialogDescription>
                        <p v-if="archiveError" class="text-sm text-destructive">
                            {{ archiveError }}
                        </p>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="showDeleteVersionDialog = false"
                            >Cancel</Button
                        >
                        <Button variant="destructive" @click="deleteVersion"
                            >Delete</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete package dialog -->
            <Dialog v-model:open="showDeleteDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Package</DialogTitle>
                        <DialogDescription>
                            This will permanently delete
                            <strong>{{ package_.full_name }}</strong> and all
                            its versions.
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
                        <Button variant="destructive" @click="deletePackage"
                            >Delete</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
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
    package: Object,
});

// "package" is a reserved word in JS, alias it
const package_ = props.package;

const showDeleteDialog = ref(false);
const showDeleteVersionDialog = ref(false);
const selectedVersion = ref(null);
const archiveError = ref(null);

function formatDate(date) {
    return new Date(date).toLocaleDateString();
}

function confirmDeleteVersion(v) {
    selectedVersion.value = v;
    archiveError.value = null;
    showDeleteVersionDialog.value = true;
}

function deleteVersion() {
    router.delete(
        route("repositories.packages.versions.destroy", [
            props.repository.slug,
            package_.id,
            selectedVersion.value.id,
        ]),
        {
            onError: (errors) => (archiveError.value = errors.archive),
            onSuccess: () => (showDeleteVersionDialog.value = false),
        },
    );
}

function deletePackage() {
    archiveError.value = null;
    router.delete(
        route("repositories.packages.destroy", [
            props.repository.slug,
            package_.id,
        ]),
        {
            onError: (errors) => (archiveError.value = errors.archive),
        },
    );
}
</script>
