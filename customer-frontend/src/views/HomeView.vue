<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" @search="onSearch" />

    <main>
      <HeroBanner />

      <FeaturedProducts
        :products="filteredProducts"
        :categories="categories"
        :active-category="activeCategory"
        @change-category="activeCategory = $event"
      />
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import HeroBanner from "@/components/home/HeroBanner.vue";
import FeaturedProducts from "@/components/home/FeaturedProducts.vue";

const cartCount = ref(2);
const user = ref({ name: "Guest", avatar: "https://i.pravatar.cc/80?img=12" });

const keyword = ref("");
const activeCategory = ref("Tất cả");

const products = ref([
  {
    id: 1,
    name: "Bút gel Pastel 0.5mm",
    category: "Bút",
    price: 19000,
    oldPrice: 25000,
    rating: 4.8,
    sold: 1200,
    badge: "Hot",
    image:
      "https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: 2,
    name: "Sổ kẻ ngang A5 (bìa cứng)",
    category: "Sổ",
    price: 39000,
    rating: 4.7,
    sold: 860,
    badge: "New",
    image:
      "https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: 3,
    name: "Sticker set dễ thương (50pcs)",
    category: "Sticker",
    price: 29000,
    rating: 4.9,
    sold: 2100,
    badge: "-15%",
    image:
      "https://images.unsplash.com/photo-1519337265831-281ec6cc8514?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: 4,
    name: "Thước kẻ + Êke mini",
    category: "Dụng cụ",
    price: 25000,
    rating: 4.6,
    sold: 540,
    image:
      "https://images.unsplash.com/photo-1588072432904-843af37f03ed?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: 5,
    name: "Bút highlight 2 đầu",
    category: "Bút",
    price: 22000,
    rating: 4.7,
    sold: 980,
    image:
      "https://images.unsplash.com/photo-1526378722484-bd91ca387e72?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: 6,
    name: "Giấy note Pastel (xấp 100)",
    category: "Giấy note",
    price: 18000,
    rating: 4.8,
    sold: 1300,
    image:
      "https://images.unsplash.com/photo-1498079022511-d15614cb1c02?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: 7,
    name: "Bìa hồ sơ trong suốt (10 cái)",
    category: "Dụng cụ",
    price: 35000,
    rating: 4.5,
    sold: 420,
    image:
      "https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: 8,
    name: "Sổ planner tuần",
    category: "Sổ",
    price: 59000,
    rating: 4.7,
    sold: 610,
    badge: "Best",
    image:
      "https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=800&q=80",
  },
]);

const categories = computed(() => {
  const set = new Set(products.value.map((p) => p.category));
  return ["Tất cả", ...Array.from(set)];
});

const filteredProducts = computed(() => {
  const k = keyword.value.toLowerCase();
  return products.value.filter((p) => {
    const matchKeyword = !k || p.name.toLowerCase().includes(k);
    const matchCategory =
      activeCategory.value === "Tất cả" || p.category === activeCategory.value;
    return matchKeyword && matchCategory;
  });
});

function onSearch(k) {
  keyword.value = k || "";
}
</script>
