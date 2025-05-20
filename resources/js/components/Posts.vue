<template>
    <div class="max-w-4xl mx-auto p-4">
        <h1 class="text-3xl font-bold- mb-6">Posts</h1>
        <form
            action=""
            class="mb-6 bg-white p-4 shadow-md rounded-md"
            @submit.prevent="savePost"
        >
            <div class="mb-4">
                <input
                    type="text"
                    placeholder="title"
                    v-model="form.title"
                    class="w-full p-2 rounded-md focus:outline-none focus:ring focus:ring-indigo-300 border border-gray-300"
                />
            </div>
            <div class="mb-4">
                <textarea
                    v-model="form.description"
                    placeholder="Content"
                    class="w-full p-2 rounded-md focus:outline-none focus:ring focus:ring-indigo-300 border border-gray-300"
                >
                </textarea>
            </div>
            <button
                type="submit"
                class="bg-indigo-500 text-white px-4 py-2 rounded-md hover:bg-indigo-600"
            >
                {{ editMode ? "Update" : "Save" }}
            </button>
        </form>
        <div
            v-for="post in posts.data"
            :key="post.id"
            class="mb-4 bg-white p-4 shadow-md rounded-md"
        >
            <h3 class="text-xl font-semibold">
                {{ post.title }}
            </h3>
            <p class="text-gray-700">
                {{ post.description }}
            </p>
            <button
                type="button"
                class="bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600"
                @click="editPost(post)"
            >
                Edit
            </button>
            <button
                type="button"
                class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 ml-3 mt-3"
                @click="deletePost(post.id)"
            >
                Delete
            </button>
        </div>
        <!-- Paginations -->
        <div
            if="posts.link"
            class="flex justify-center items-center space-x-2 mt-6"
        >
            <button
                v-for="(link, index) in posts.links"
                :key="index"
                @click="fetchPosts(link.url)"
                :disabled="!link.url"
                class="px-4 py-2 rounded-md"
                :class="{
                    'bg-indigo-500 text-white  hover:bg-indigo-600 ':
                        link.active,
                    'bg-gray-500 text-white  hover:bg-gray-600 ':
                        !link.active && link.url,
                    'bg-gray-300 text-gray-600  cursor-not-allowed ': !link.url,
                }"
                v-html="link.label"
            ></button>
        </div>
    </div>
</template>

<script>
import axios from "axios";
export default {
    data() {
        return {
            posts: {},
            form: {
                title: "",
                description: "",
            },
            editMode: false,
            editId: null,
        };
    },
    methods: {
        async fetchPosts(url = "/api/posts") {
            const { data } = await axios.get(url);
            this.posts = data;
        },
        async savePost() {
            if (this.editMode) {
                await axios.put(`/api/posts/${this.editId}`, this.form);
                this.editMode = false;
            } else {
                await axios.post("/api/posts", this.form);
            }
            this.form = {
                title: "",
                description: "",
            };
            this.fetchPosts();
        },
        async editPost(post) {
            this.form = {
                title: post.title,
                description: post.description,
            };
            this.editId = post.id;
            this.editMode = true;
        },
        async deletePost(id) {
            await axios.delete(`/api/posts/${id}`);
            this.fetchPosts();
        },
    },
    mounted() {
        this.fetchPosts();
    },
};
</script>
