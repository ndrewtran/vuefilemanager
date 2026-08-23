<template>
    <div class="menu-options">
        <slot />
    </div>
</template>

<script>
export default {
    name: 'MenuMobileGroup',
}
</script>

<style scoped lang="scss">
@import '../../../sass/vuefilemanager/variables';
@import '../../../sass/vuefilemanager/mixins';

.menu-options {
    margin-top: 10px;
    list-style: none;
    width: 100%;
}
</style>
