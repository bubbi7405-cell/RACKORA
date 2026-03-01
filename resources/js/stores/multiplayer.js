import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';

export const useMultiplayerStore = defineStore('multiplayer', () => {
    const availableRentals = ref([]);
    const myRentalsAsProvider = ref([]);
    const myRentalsAsTenant = ref([]);
    const isLoading = ref(false);

    async function loadAvailableRentals() {
        isLoading.value = true;
        try {
            const res = await api.get('/multiplayer/rentals/available');
            if (res.success) {
                availableRentals.value = res.data;
            }
        } catch (e) {
            console.error(e);
        } finally {
            isLoading.value = false;
        }
    }

    async function loadMyRentals() {
        try {
            const res = await api.get('/multiplayer/rentals/my');
            if (res.success) {
                myRentalsAsProvider.value = res.data.asProvider;
                myRentalsAsTenant.value = res.data.asTenant;
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function listServerForRent(serverId, price) {
        const toast = useToastStore();
        try {
            const res = await api.post('/multiplayer/rentals/list', {
                serverId,
                pricePerHour: price
            });
            if (res.success) {
                toast.success('Server listed successfully!');
                return true;
            }
        } catch (e) {
            toast.error(e.response?.data?.error || 'Listing failed');
        }
        return false;
    }

    async function rentServer(rentalId) {
        const toast = useToastStore();
        try {
            const res = await api.post('/multiplayer/rentals/rent', { rentalId });
            if (res.success) {
                toast.success('Server rented! It is now available in your infrastructure.');
                loadAvailableRentals();
                loadMyRentals();
                return true;
            }
        } catch (e) {
            toast.error(e.response?.data?.error || 'Rental failed');
        }
        return false;
    }

    async function terminateRental(rentalId) {
        const toast = useToastStore();
        try {
            const res = await api.post(`/multiplayer/rentals/${rentalId}/terminate`);
            if (res.success) {
                toast.warning('Rental terminated.');
                loadMyRentals();
                return true;
            }
        } catch (e) {
            toast.error(e.response?.data?.error || 'Termination failed');
        }
        return false;
    }

    return {
        availableRentals,
        myRentalsAsProvider,
        myRentalsAsTenant,
        isLoading,
        loadAvailableRentals,
        loadMyRentals,
        listServerForRent,
        rentServer,
        terminateRental
    };
});
