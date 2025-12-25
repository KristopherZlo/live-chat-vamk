export type RoomMeta = {
    slug: string;
    title: string;
    description: string;
    owner: string;
};

export type StoredRoom = Partial<RoomMeta>;
